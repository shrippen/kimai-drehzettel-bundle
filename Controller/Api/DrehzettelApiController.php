<?php

namespace KimaiPlugin\DrehzettelBundle\Controller\Api;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Enum\Catering;
use KimaiPlugin\DrehzettelBundle\Enum\DayType;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API for external clients (e.g. the Plasmai KDE Plasma widget) that want to
 * adapt their own UI to whether a timesheet entry is a Drehzettel film day,
 * without reimplementing engagement/ruleset lookup themselves.
 *
 * Plan and rationale: research/api-external-clients.md. Verified against
 * Kimai 2.67.0: the /api path prefix, #[IsGranted('API')] auth and the
 * OpenAPI doc scan are all pattern-matched on the route path, not on the
 * src/API/ directory, so this controller gets all three for free by living
 * under /api/drehzettel/... - see that file for the source citations.
 *
 * Versioned under /v1: a future incompatible change gets a new /v2 prefix
 * so existing external clients keep working against /v1 unchanged.
 */
#[Route(path: '/drehzettel')]
#[IsGranted('API')]
#[OA\Tag(name: 'Drehzettel')]
final class DrehzettelApiController extends AbstractController
{
    // Bump alongside composer.json's "version" when the plugin changes in a way external
    // clients might care about; bump API_VERSIONS when a new /v{n} prefix is introduced.
    private const PLUGIN_VERSION = '0.1.0';
    private const API_VERSIONS = ['v1'];

    public function __construct(
        private readonly EngagementService $engagements,
        private readonly EngagementAccess $access,
        private readonly FilmDayRepository $filmDays,
        private readonly FilmDayService $filmDayService,
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
        private readonly Security $security,
    ) {
    }

    #[Route(methods: ['GET'], path: '/ping', name: 'drehzettel_api_ping')]
    #[OA\Response(response: 200, description: 'Whether the Drehzettel plugin is installed on this Kimai instance, and which API versions it serves.')]
    public function ping(): JsonResponse
    {
        return new JsonResponse([
            'installed' => true,
            'pluginVersion' => self::PLUGIN_VERSION,
            'apiVersions' => self::API_VERSIONS,
        ]);
    }

    #[Route(methods: ['GET'], path: '/v1/engagement-status', name: 'drehzettel_api_engagement_status')]
    #[OA\Response(response: 200, description: 'Whether project+user+date fall inside an active engagement, and its toggle default for a client-side "film day" UI switch.')]
    public function engagementStatus(Request $request): JsonResponse
    {
        $project = $this->requireProject($request);
        $user = $this->requireUser($request);
        $date = $this->dateFromQuery($request);

        $this->assertCanQuery($user);

        $engagement = $this->engagements->active($user, $project, $date);

        return new JsonResponse([
            'active' => $engagement !== null,
            'engagementId' => $engagement?->getId(),
            'toggleDefault' => $engagement !== null,
            'rulesetName' => $engagement?->getRulesetName(),
        ]);
    }

    #[Route(methods: ['GET'], path: '/v1/film-days/{date}', name: 'drehzettel_api_film_day_get', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    #[OA\Response(response: 200, description: 'The stored film day fields for this date, or nulls/defaults if none were saved yet. A null breakMinutes/category means "use the ruleset default".')]
    public function filmDayGet(Request $request, string $date): JsonResponse
    {
        $engagement = $this->requireEngagement($request, $date);
        $day = $this->filmDays->findOne($engagement, $this->parseDate($date));

        return new JsonResponse($this->filmDayJson($date, $engagement, $day));
    }

    #[Route(methods: ['PUT'], path: '/v1/film-days/{date}', name: 'drehzettel_api_film_day_put', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    #[OA\Response(response: 200, description: 'Saves the film day fields for this date (upsert). Partial: only keys present in the body change, others keep their stored value. null resets breakMinutes/category/productionDay/note to the ruleset default.')]
    #[OA\Response(response: 400, description: 'Invalid JSON or field value: breakMinutes 0-720, productionDay 1-7, note at most 500 characters, catering boolean, category/dayType one of the known values.')]
    public function filmDayPut(Request $request, string $date): JsonResponse
    {
        $engagement = $this->requireEngagement($request, $date);

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $patch = FilmDayPatch::fromArray($body);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $day = $this->filmDayService->patch($engagement, $this->parseDate($date), $patch);

        return new JsonResponse($this->filmDayJson($date, $engagement, $day));
    }

    // Shared GET/PUT shape. dayType/productionDay were added later: clients must ignore unknown keys.
    private function filmDayJson(string $date, Engagement $engagement, ?FilmDay $day): array
    {
        return [
            'date' => $date,
            'engagementId' => $engagement->getId(),
            'breakMinutes' => $day?->getBreakMinutes(),
            'catering' => ($day?->getCatering() ?? Catering::NO) === Catering::YES,
            'category' => $day?->getCategory()?->value,
            'note' => $day?->getNote(),
            'dayType' => ($day?->getDayType() ?? DayType::WORKDAY)->value,
            'productionDay' => $day?->getProductionDay(),
        ];
    }

    // engagement-status may legitimately answer "false" for a user/project with no
    // engagement at all, so there is no Engagement to check assertView() against here -
    // same rule restated directly: your own data, or manage permission for someone else's.
    private function assertCanQuery(\App\Entity\User $user): void
    {
        if ($this->access->canManage()) {
            return;
        }
        $requester = $this->security->getUser();
        if ($requester instanceof \App\Entity\User && $requester->getId() === $user->getId()) {
            return;
        }

        throw $this->createAccessDeniedException('Querying another user\'s engagement status needs the permission drehzettel_manage.');
    }

    private function requireEngagement(Request $request, string $date): Engagement
    {
        $project = $this->requireProject($request);
        $user = $this->requireUser($request);

        $engagement = $this->engagements->active($user, $project, $this->parseDate($date));
        if ($engagement === null) {
            throw $this->createNotFoundException('No active engagement for this project, user and date.');
        }
        $this->access->assertView($engagement);

        return $engagement;
    }

    private function requireProject(Request $request): \App\Entity\Project
    {
        $id = $request->query->get('project');
        $project = $id !== null ? $this->projects->find((int) $id) : null;
        if ($project === null) {
            throw $this->createNotFoundException('Unknown or missing project.');
        }

        return $project;
    }

    private function requireUser(Request $request): \App\Entity\User
    {
        $id = $request->query->get('user');
        if ($id === null) {
            $user = $this->security->getUser();
            \assert($user instanceof \App\Entity\User);

            return $user;
        }

        $user = $this->users->find((int) $id);
        if ($user === null) {
            throw $this->createNotFoundException('Unknown user.');
        }

        return $user;
    }

    private function dateFromQuery(Request $request): \DateTimeImmutable
    {
        $date = $request->query->get('date');

        return $date !== null ? $this->parseDate((string) $date) : new \DateTimeImmutable('today');
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        // '!' zeroes the time; the round trip rejects overflow dates like 2025-02-30.
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw $this->createNotFoundException('Invalid date, expected YYYY-MM-DD.');
        }

        return $parsed;
    }
}
