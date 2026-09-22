<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use KimaiPlugin\DrehzettelBundle\Domain\PayTerms;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Enum\PayKind;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel/engagement')]
#[IsGranted('drehzettel_manage')]
class EngagementController extends AbstractController
{
    private const CSRF_ID = 'drehzettel_engagement';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementService $service,
        private readonly RulesetCatalog $catalog,
        private readonly UserRepository $users,
        private readonly ProjectRepository $projects,
    ) {
    }

    #[Route(path: '/new', name: 'drehzettel_engagement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $user = $this->users->find((int) $request->request->get('user'));
            $project = $this->projects->find((int) $request->request->get('project'));
            if ($user === null || $project === null) {
                $this->flashError('action.update.error');

                return $this->redirectToRoute('drehzettel_engagement_new');
            }

            try {
                $engagement = $this->service->open(
                    $user,
                    $project,
                    (string) $request->request->get('role'),
                    $this->termsFromRequest($request),
                    new \DateTimeImmutable((string) $request->request->get('valid_from')),
                    $this->optionalDate($request->request->get('valid_to')),
                    (string) $request->request->get('ruleset'),
                );
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('drehzettel_week', ['id' => $engagement->getId()]);
            } catch (\DomainException|\InvalidArgumentException $e) {
                $this->flashError('action.update.error', $e->getMessage());
            }
        }

        return $this->render('@Drehzettel/drehzettel/engagement_form.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'engagement' => null,
            'rulesets' => $this->rulesetOptions(),
            'users' => $this->users->findBy(['enabled' => true], ['username' => 'ASC']),
            'projects' => $this->projects->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'drehzettel_engagement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $engagement = $this->find($id);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $engagement->setRole((string) $request->request->get('role'));
            $terms = $this->termsFromRequest($request);
            $engagement->setPayKind($terms->kind);
            $engagement->setGageCents($terms->gageCents);
            $engagement->setCateringDeductionCents($terms->cateringDeductionCents);
            $engagement->setValidFrom(new \DateTimeImmutable((string) $request->request->get('valid_from')));
            $engagement->setValidTo($this->optionalDate($request->request->get('valid_to')));

            try {
                $this->service->save($engagement);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('drehzettel_week', ['id' => $engagement->getId()]);
            } catch (\DomainException $e) {
                $this->flashError('action.update.error', $e->getMessage());
            }
        }

        return $this->render('@Drehzettel/drehzettel/engagement_form.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'engagement' => $engagement,
            'rulesets' => $this->rulesetOptions(),
        ]);
    }

    #[Route(path: '/{id}/rules', name: 'drehzettel_engagement_rules', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function rules(Request $request, int $id): Response
    {
        $engagement = $this->find($id);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            try {
                $rules = RulesetFormMapper::fromForm($request->request->all());
                $this->service->replaceRules($engagement, $rules);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('drehzettel_week', ['id' => $engagement->getId()]);
            } catch (\Throwable $e) {
                $this->flashError('action.update.error', $e->getMessage());
            }
        }

        return $this->render('@Drehzettel/drehzettel/ruleset_form.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'engagement' => $engagement,
            'form' => RulesetFormMapper::toForm($this->service->ruleset($engagement)),
            'target' => 'drehzettel_engagement_rules',
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'drehzettel_engagement_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $engagement = $this->find($id);
        if ($this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $this->service->remove($engagement);
            $this->flashSuccess('action.delete.success');
        }

        return $this->redirectToRoute('drehzettel_overview');
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    private function rulesetOptions(): array
    {
        return array_map(fn (string $key): array => ['key' => $key, 'name' => $this->catalog->get($key)->name], $this->catalog->keys());
    }

    private function find(int $id): Engagement
    {
        $engagement = $this->engagements->find($id);
        if ($engagement === null) {
            throw $this->createNotFoundException('Unknown engagement.');
        }

        return $engagement;
    }

    private function termsFromRequest(Request $request): PayTerms
    {
        $kind = PayKind::from((string) $request->request->get('pay_kind', PayKind::WEEKLY->value));
        $gage = (float) str_replace(',', '.', (string) $request->request->get('gage', '0'));
        $catering = (float) str_replace(',', '.', (string) $request->request->get('catering_deduction', '0'));

        return new PayTerms($kind, (int) round($gage * 100), (int) round($catering * 100));
    }

    private function optionalDate(mixed $value): ?\DateTimeImmutable
    {
        $text = trim((string) $value);

        return $text === '' ? null : new \DateTimeImmutable($text);
    }
}
