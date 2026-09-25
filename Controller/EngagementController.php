<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\Project;
use App\Repository\ActivityRepository;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Form\EngagementData;
use KimaiPlugin\DrehzettelBundle\Form\EngagementType;
use KimaiPlugin\DrehzettelBundle\Form\RulesetType;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementService;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Engagement create/edit/delete in Kimai's modal (pages without JS),
 * rules of one engagement on the ruleset editor page.
 */
#[Route(path: '/drehzettel/engagement')]
#[IsGranted('drehzettel_manage')]
class EngagementController extends AbstractController
{
    use KpuFormSuccessTrait;

    private const FORM_ACTIONS = 'drehzettel_form';

    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementService $service,
        private readonly RulesetCatalog $catalog,
        private readonly ActivityRepository $activities,
        private readonly PageSetups $pages,
    ) {
    }

    #[Route(path: '/new', name: 'drehzettel_engagement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $data = new EngagementData();
        $data->ruleset = RulesetCatalog::TV_FFS_2024;
        $data->validFrom = new \DateTimeImmutable('today');

        $form = $this->createForm(EngagementType::class, $data, [
            'action' => $this->generateUrl('drehzettel_engagement_new'),
            'rulesets' => $this->rulesetChoices(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $engagement = $this->service->open($data->user, $data->project, (string) $data->role, $data->terms(), $data->validFrom, $data->validTo, (string) $data->ruleset);
                $engagement->setActivityIds($this->activityIdsFromRequest($request));
                $this->service->save($engagement);
                $this->flashSuccess('action.update.success');

                // New engagement: open its week page.
                return $this->kpuFormSuccess($request, 'drehzettel_week', ['id' => $engagement->getId()]);
            } catch (\DomainException) {
                $this->overlapError($form);
            }
        }

        // Project isn't chosen yet at render time, so list every visible activity;
        // each option's label carries its project so the right one is still findable.
        return $this->renderEditor($form, 'drehzettel.engagement.new', $this->generateUrl('drehzettel_overview'), null, $this->activities->findBy(['visible' => true], ['name' => 'ASC']), []);
    }

    #[Route(path: '/{id}/edit', name: 'drehzettel_engagement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $engagement = $this->find($id);
        $data = EngagementData::fromEngagement($engagement);

        $form = $this->createForm(EngagementType::class, $data, [
            'action' => $this->generateUrl('drehzettel_engagement_edit', ['id' => $id]),
            'currency' => $engagement->getProject()?->getCustomer()?->getCurrency(),
            'attr' => ['data-form-event' => 'kpu.reload'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data->applyTo($engagement);
                $engagement->setActivityIds($this->activityIdsFromRequest($request));
                $this->service->save($engagement);
                $this->flashSuccess('action.update.success');

                // Opened from the overview or a week: reload that page, keeping its week.
                return $this->kpuFormSuccess($request, 'drehzettel_week', ['id' => $engagement->getId()], true);
            } catch (\DomainException) {
                $this->overlapError($form);
            }
        }

        // The project is fixed once an engagement exists - scope the picker to its
        // activities (plus global, project-less ones) instead of every activity in Kimai.
        return $this->renderEditor($form, 'drehzettel.engagement.edit', $this->generateUrl('drehzettel_week', ['id' => $id]), $engagement, $this->activitiesForProject($engagement->getProject()), $engagement->getActivityIds());
    }

    #[Route(path: '/{id}/rules', name: 'drehzettel_engagement_rules', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function rules(Request $request, int $id): Response
    {
        $engagement = $this->find($id);
        $back = $this->generateUrl('drehzettel_week', ['id' => $id]);

        $form = $this->createForm(RulesetType::class, RulesetFormMapper::toForm($this->service->ruleset($engagement)), [
            'csrf_token_id' => 'drehzettel_engagement',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $data['name'] = $engagement->getRulesetName();
                $this->service->replaceRules($engagement, RulesetFormMapper::fromForm($data));
                $this->flashSuccess('action.update.success');

                return $this->redirect($back);
            } catch (\DomainException|\ValueError $e) {
                $this->logException($e);
                $form->addError(new FormError($this->pages->trans('drehzettel.rules.invalid')));
            }
        }

        return $this->render('@Drehzettel/drehzettel/ruleset_form.html.twig', [
            'page_setup' => $this->pages->create(self::FORM_ACTIONS, $this->pages->trans('drehzettel.rules.edit'), ['back' => $back]),
            'form' => $form->createView(),
            'title' => $engagement->getRulesetName(),
            'context' => [$engagement->getProject()?->getName(), $engagement->getRole(), $engagement->getUser()?->getDisplayName()],
            'back' => $back,
        ]);
    }

    // Kimai delete confirmation (modal or page), POST deletes.
    #[Route(path: '/{id}/delete', name: 'drehzettel_engagement_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $engagement = $this->find($id);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'drehzettel_engagement'])
            ->setAction($this->generateUrl('drehzettel_engagement_delete', ['id' => $id]))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->service->remove($engagement);
            $this->flashSuccess('action.delete.success');

            // Also from the week page of this engagement, which no longer exists.
            return $this->kpuFormSuccess($request, 'drehzettel_overview');
        }

        return $this->render('@Drehzettel/drehzettel/delete.html.twig', [
            'page_setup' => $this->pages->create(self::FORM_ACTIONS, $this->pages->trans('action.delete')),
            'form' => $form->createView(),
            'item' => sprintf('%s · %s · %s', $engagement->getProject()?->getName(), $engagement->getRole(), $engagement->getUser()?->getDisplayName()),
            'message' => $this->pages->trans('drehzettel.engagement.delete_warning'),
            'back' => $this->generateUrl('drehzettel_week', ['id' => $id]),
        ]);
    }

    /**
     * @param list<Activity> $activities
     * @param list<int>      $selectedActivityIds
     */
    private function renderEditor(FormInterface $form, string $titleKey, string $back, ?Engagement $engagement = null, array $activities = [], array $selectedActivityIds = []): Response
    {
        $title = $this->pages->trans($titleKey);

        return $this->render('@Drehzettel/drehzettel/engagement_form.html.twig', [
            'page_setup' => $this->pages->create(self::FORM_ACTIONS, $title, ['back' => $back]),
            'form' => $form->createView(),
            'title' => $title,
            'engagement' => $engagement,
            'back' => $back,
            'activities' => $activities,
            'selectedActivityIds' => $selectedActivityIds,
        ]);
    }

    private function overlapError(FormInterface $form): void
    {
        $form->get('validFrom')->addError(new FormError($this->pages->trans('drehzettel.engagement.overlap')));
    }

    /**
     * @return array<string, string> ruleset name => key
     */
    private function rulesetChoices(): array
    {
        $choices = [];
        foreach ($this->catalog->keys() as $key) {
            $choices[$this->catalog->get($key)->name] = $key;
        }

        return $choices;
    }

    /**
     * @return list<int>
     */
    private function activityIdsFromRequest(Request $request): array
    {
        $ids = $request->request->all('activities');
        if (!\is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => (string) $id !== ''))));
    }

    /**
     * Activities available to restrict an engagement to: this project's own activities
     * plus every global (project-less) one - not every activity in the Kimai instance.
     *
     * @return list<Activity>
     */
    private function activitiesForProject(?Project $project): array
    {
        $ownActivities = $project !== null ? $this->activities->findBy(['project' => $project], ['name' => 'ASC']) : [];
        $globalActivities = $this->activities->findBy(['project' => null], ['name' => 'ASC']);

        return [...$ownActivities, ...$globalActivities];
    }

    private function find(int $id): Engagement
    {
        $engagement = $this->engagements->find($id);
        if ($engagement === null) {
            throw $this->createNotFoundException('Unknown engagement.');
        }

        return $engagement;
    }
}
