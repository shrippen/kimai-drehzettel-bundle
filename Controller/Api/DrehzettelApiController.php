<?php

namespace KimaiPlugin\DrehzettelBundle\Controller\Api;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use KimaiPlugin\DrehzettelBundle\Domain\ApiError;
use KimaiPlugin\DrehzettelBundle\Domain\ApiInfo;
use KimaiPlugin\DrehzettelBundle\Domain\ApiJson;
use KimaiPlugin\DrehzettelBundle\Domain\ApiQuery;
use KimaiPlugin\DrehzettelBundle\Domain\FilmDayPatch;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;
use KimaiPlugin\DrehzettelBundle\Service\AzvService;
use KimaiPlugin\DrehzettelBundle\Service\DayInputBuilder;
use KimaiPlugin\DrehzettelBundle\Service\DaySummaryService;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\FilmDayService;
use KimaiPlugin\DrehzettelBundle\Repository\FilmDayRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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
 *
 * Errors: {"error": text, "code": machine code}, see Domain\ApiError.
 * 400 bad syntax, 403 someone else's data, 404 unknown id or no engagement.
 */
#[Route(path: '/drehzettel')]
#[IsGranted('API')]
#[OA\Tag(name: 'Drehzettel')]
final class DrehzettelApiController extends AbstractController
{
    public function __construct(
        private readonly EngagementService $engagements,
        private readonly EngagementAccess $access,
        private readonly FilmDayRepository $filmDays,
        private readonly FilmDayService $filmDayService,
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
        private readonly Security $security,
        private readonly DayInputBuilder $dayInputs,
        private readonly DaySummaryService $daySummaries,
        private readonly AzvService $azv,
    ) {
    }

    #[Route(methods: ['GET'], path: '/ping', name: 'drehzettel_api_ping')]
    #[OA\Response(response: 200, description: 'Whether the Drehzettel plugin is installed on this Kimai instance, which API versions and v1 additions ("features") it serves, and the token owner\'s permissions: view = own engagements, manage = everyone\'s.')]
    public function ping(): JsonResponse
    {
        return new JsonResponse(ApiInfo::ping([
            'view' => $this->access->canUse(),
            'manage' => $this->access->canManage(),
        ]));
    }

    #[Route(methods: ['GET'], path: '/v1/engagement-status', name: 'drehzettel_api_engagement_status')]
    #[OA\Response(response: 200, description: 'Whether project+user+date fall inside an active engagement, and its toggle default for a client-side "film day" UI switch. No engagement is {active: false}, not a 404.')]
    #[OA\Response(response: 400, description: 'code missing_project, invalid_project, invalid_user or invalid_date.')]
    #[OA\Response(response: 404, description: 'code unknown_project or unknown_user.')]
    public function engagementStatus(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request): array {
            $project = $this->requireProject($request);
            $user = $this->requireUser($request);
            $date = ApiQuery::date($request->query->get('date'), new \DateTimeImmutable('today'));

            $this->assertCanQuery($user);

            $engagement = $this->engagements->active($user, $project, $date);

            return [
                'active' => $engagement !== null,
                'engagementId' => $engagement?->getId(),
                'toggleDefault' => $engagement !== null,
                'rulesetName' => $engagement?->getRulesetName(),
            ];
        });
    }

    #[Route(methods: ['GET'], path: '/v1/engagements', name: 'drehzettel_api_engagements')]
    #[OA\Response(response: 200, description: 'Engagements of the user (default: token owner) active on date (default: today), by project name: [{engagementId, projectId, projectName, customerName, rulesetName, crewRole, validFrom, validTo, toggleDefault, azvEligible, travelDays}]. azvEligible: the engagement earns AZV credit (TV FFS TZ 6), see /v1/engagements/{id}/azv. travelDays: excluded (tariff, TZ 12.1) or counted, whether travel days count for the 6th/7th day and weekly overtime (ruleset option).')]
    #[OA\Response(response: 400, description: 'code invalid_user or invalid_date.')]
    #[OA\Response(response: 403, description: 'code forbidden: another user without drehzettel_manage.')]
    #[OA\Response(response: 404, description: 'code unknown_user.')]
    public function engagementList(Request $request): JsonResponse
    {
        return $this->respond(function () use ($request): array {
            $user = $this->requireUser($request);
            $date = ApiQuery::date($request->query->get('date'), new \DateTimeImmutable('today'));
            $this->assertCanQuery($user);

            // Own engagements still need the "drehzettel" permission, as in the web UI.
            $visible = array_filter($this->engagements->activeOn($user, $date), $this->access->canView(...));

            return array_values(array_map(fn (Engagement $e): array => ApiJson::engagement($e, $this->engagements->ruleset($e)), $visible));
        });
    }

    #[Route(methods: ['GET'], path: '/v1/engagements/{id}/azv', name: 'drehzettel_api_engagement_azv', requirements: ['id' => '\d+'])]
    #[OA\Response(response: 200, description: 'AZV credit (TV FFS TZ 6) earned up to and including date (default: today): {engagementId, eligible, countsFrom, date, shootingDays, minutes, days, openMinutes, dayMinutes, blockDays}. 2.5 h after 5 shooting days, 0.5 h per further one, per block of 20 shooting days (= 600 minutes = one AZV day). Travel days and days without entry do not count; counting starts at the engagement start, not before 2025-05-01. Not eligible: eligible false, shootingDays 0. Credit only: AZV days taken are not deducted.')]
    #[OA\Response(response: 400, description: 'code invalid_date.')]
    #[OA\Response(response: 403, description: 'code forbidden: someone else\'s engagement without drehzettel_manage.')]
    #[OA\Response(response: 404, description: 'code unknown_engagement.')]
    public function engagementAzv(Request $request, int $id): JsonResponse
    {
        return $this->respond(function () use ($request, $id): array {
            $date = ApiQuery::date($request->query->get('date'), new \DateTimeImmutable('today'));
            $engagement = $this->engagements->find($id) ?? throw ApiError::notFound(ApiError::UNKNOWN_ENGAGEMENT, 'Unknown engagement.');
            $this->access->assertView($engagement);

            return ApiJson::azv($engagement, $this->azv->balance($engagement, $date->modify('+1 day')));
        });
    }

    #[Route(methods: ['GET'], path: '/v1/film-days/{date}', name: 'drehzettel_api_film_day_get', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    #[OA\Response(response: 200, description: 'The stored film day fields for this date, or nulls/defaults if none were saved yet. A null breakMinutes/category means "use the default": defaultBreakMinutes (engagement ruleset) and effectiveCategory (weekday or public holiday, or the stored category). productionDay is the day within the shooting week, 1-7 (6/7 trigger the 6th/7th-day surcharge; null = count entries of the week). shootingDayNumber is the running shooting day of the production (1-999, "Drehtag 37"), informational only.')]
    #[OA\Response(response: 404, description: 'code no_engagement: no active engagement for project, user and date; or unknown_project/unknown_user.')]
    public function filmDayGet(Request $request, string $date): JsonResponse
    {
        return $this->respond(function () use ($request, $date): array {
            $engagement = $this->requireEngagement($request, $date);
            $day = $this->filmDays->findOne($engagement, ApiQuery::date($date, new \DateTimeImmutable()));

            return $this->filmDayJson($date, $engagement, $day);
        });
    }

    #[Route(methods: ['PUT'], path: '/v1/film-days/{date}', name: 'drehzettel_api_film_day_put', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    #[OA\Response(response: 200, description: 'Saves the film day fields for this date (upsert). Partial: only keys present in the body change, others keep their stored value. null resets breakMinutes/category/productionDay/note to the ruleset default, extraPayCents to 0 and shootingDayNumber to none.')]
    #[OA\Response(response: 400, description: 'code invalid_json, or invalid_value for a field: breakMinutes 0-720, productionDay 1-7, extraPayCents integer 0-10000000, shootingDayNumber 1-999, note at most 500 characters, catering boolean, category/dayType one of the known values.')]
    #[OA\Response(response: 404, description: 'code no_engagement, unknown_project or unknown_user.')]
    public function filmDayPut(Request $request, string $date): JsonResponse
    {
        return $this->respond(function () use ($request, $date): array {
            $engagement = $this->requireEngagement($request, $date);

            $body = json_decode($request->getContent(), true);
            if (!is_array($body)) {
                throw ApiError::badRequest(ApiError::INVALID_JSON, 'Invalid JSON body.');
            }

            try {
                $patch = FilmDayPatch::fromArray($body);
            } catch (\InvalidArgumentException $e) {
                throw ApiError::badRequest(ApiError::INVALID_VALUE, $e->getMessage());
            }

            $day = $this->filmDayService->patch($engagement, ApiQuery::date($date, new \DateTimeImmutable()), $patch);

            return $this->filmDayJson($date, $engagement, $day);
        });
    }

    #[Route(methods: ['GET'], path: '/v1/days/{date}/summary', name: 'drehzettel_api_day_summary', requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    #[OA\Response(response: 200, description: 'Calculated figures of the day, from its whole ISO week: work/break/night/under minutes, daily overtime per tier [{percent, minutes}], category surcharge, week pool of weekly overtime, compliance warnings, payCents (day pay incl. extra pay, excl. weekly overtime; null without gage), shootingDayNumber (informational), azvMinutesToDate (AZV credit up to and including the date, TV FFS TZ 6; null when the engagement earns none). hasEntry false: no timesheet entry on that date, all figures 0/null.')]
    #[OA\Response(response: 404, description: 'code no_engagement, unknown_project or unknown_user.')]
    public function daySummary(Request $request, string $date): JsonResponse
    {
        return $this->respond(function () use ($request, $date): array {
            $engagement = $this->requireEngagement($request, $date);

            return $this->daySummaries->summary($engagement, ApiQuery::date($date, new \DateTimeImmutable()));
        });
    }

    /**
     * Runs one action; known failures become {error, code} with their status.
     *
     * @param \Closure(): array<string, mixed> $action
     */
    private function respond(\Closure $action): JsonResponse
    {
        try {
            return new JsonResponse($action());
        } catch (AccessDeniedException $e) {
            $error = ApiError::forbidden($e->getMessage());
        } catch (ApiError $e) {
            $error = $e;
        }

        return new JsonResponse($error->body(), $error->status);
    }

    // Shared GET/PUT shape, with the defaults a null field falls back to.
    private function filmDayJson(string $date, Engagement $engagement, ?FilmDay $day): array
    {
        $rules = $this->engagements->ruleset($engagement);
        $autoCategory = $this->dayInputs->categoryFor($engagement, ApiQuery::date($date, new \DateTimeImmutable()));

        return ApiJson::filmDay($date, $engagement, $day, $rules, $autoCategory);
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

        throw ApiError::forbidden('Querying another user\'s engagements needs the permission drehzettel_manage.');
    }

    private function requireEngagement(Request $request, string $date): Engagement
    {
        $project = $this->requireProject($request);
        $user = $this->requireUser($request);
        $this->assertCanQuery($user);

        $engagement = $this->engagements->active($user, $project, ApiQuery::date($date, new \DateTimeImmutable()));
        if ($engagement === null) {
            throw ApiError::notFound(ApiError::NO_ENGAGEMENT, 'No active engagement for this project, user and date.');
        }
        $this->access->assertView($engagement);

        return $engagement;
    }

    private function requireProject(Request $request): \App\Entity\Project
    {
        $project = $this->projects->find(ApiQuery::projectId($request->query->get('project')));
        if ($project === null) {
            throw ApiError::notFound(ApiError::UNKNOWN_PROJECT, 'Unknown project.');
        }

        return $project;
    }

    private function requireUser(Request $request): \App\Entity\User
    {
        $id = ApiQuery::userId($request->query->get('user'));
        if ($id === null) {
            $user = $this->security->getUser();
            \assert($user instanceof \App\Entity\User);

            return $user;
        }

        $user = $this->users->find($id);
        if ($user === null) {
            throw ApiError::notFound(ApiError::UNKNOWN_USER, 'Unknown user.');
        }

        return $user;
    }
}
