<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Entity\FilmRuleset;
use KimaiPlugin\DrehzettelBundle\Form\RulesetType;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Custom ruleset templates: copy a preset, or an existing one, then edit it.
 * Built-in presets (TV FFS 2024, Quarter-Hour Ruleset) are read-only.
 */
#[Route(path: '/drehzettel/ruleset')]
#[IsGranted('drehzettel_manage')]
class RulesetController extends AbstractController
{
    private const CSRF_ID = 'drehzettel_ruleset';
    private const FORM_ACTIONS = 'drehzettel_form';
    private const BUILTIN = [RulesetCatalog::TV_FFS_2024, RulesetCatalog::QUARTER_HOUR];

    public function __construct(
        private readonly FilmRulesetRepository $custom,
        private readonly RulesetCatalog $catalog,
        private readonly PageSetups $pages,
    ) {
    }

    #[Route(path: '/', name: 'drehzettel_ruleset_list', methods: ['GET'])]
    public function list(): Response
    {
        $rows = array_map(fn (string $key): array => ['key' => $key, 'id' => null, 'name' => $this->catalog->get($key)->name], self::BUILTIN);
        foreach ($this->custom->findAllSorted() as $ruleset) {
            $rows[] = ['key' => $ruleset->getName(), 'id' => $ruleset->getId(), 'name' => $ruleset->getName()];
        }

        return $this->render('@Drehzettel/drehzettel/ruleset_list.html.twig', [
            'page_setup' => $this->pages->create('drehzettel_rulesets', $this->pages->trans('drehzettel.rulesets')),
            'rows' => $rows,
        ]);
    }

    #[Route(path: '/new/{from}', name: 'drehzettel_ruleset_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $from): Response
    {
        try {
            $base = $this->catalog->get($from);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Unknown ruleset.');
        }

        $data = RulesetFormMapper::toForm($base);
        $data['name'] = $this->pages->trans('drehzettel.ruleset.copy_name', ['%name%' => $base->name]);
        $form = $this->createForm(RulesetType::class, $data, ['with_name' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $name = trim((string) $data['name']);
            if ($this->catalog->isPresetKey($name) || $this->custom->findByName($name) !== null) {
                $form->get('name')->addError(new FormError($this->pages->trans('drehzettel.ruleset.name_taken')));
            } else {
                $data['name'] = $name;
                $ruleset = new FilmRuleset();
                $ruleset->setName($name);
                if ($this->store($ruleset, $data, $form)) {
                    return $this->redirectToRoute('drehzettel_ruleset_list');
                }
            }
        }

        return $this->renderEditor($form, $this->pages->trans('drehzettel.ruleset.new'), $this->pages->trans('drehzettel.ruleset.based_on', ['%name%' => $base->name]));
    }

    #[Route(path: '/{id}/edit', name: 'drehzettel_ruleset_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $ruleset = $this->find($id);

        $form = $this->createForm(RulesetType::class, RulesetFormMapper::toForm(RulesetCodec::fromArray($ruleset->getRules())));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $data['name'] = $ruleset->getName();
            if ($this->store($ruleset, $data, $form)) {
                return $this->redirectToRoute('drehzettel_ruleset_list');
            }
        }

        return $this->renderEditor($form, $ruleset->getName(), null, $this->generateUrl('drehzettel_ruleset_delete', ['id' => $id]));
    }

    // Kimai delete confirmation (modal or page), POST deletes.
    #[Route(path: '/{id}/delete', name: 'drehzettel_ruleset_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $ruleset = $this->find($id);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => self::CSRF_ID, 'attr' => ['data-form-event' => 'kimai.drehzettelRulesetDelete']])
            ->setAction($this->generateUrl('drehzettel_ruleset_delete', ['id' => $id]))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->custom->remove($ruleset);
            $this->flashSuccess('action.delete.success');

            return $this->redirectToRoute('drehzettel_ruleset_list');
        }

        return $this->render('@Drehzettel/drehzettel/delete.html.twig', [
            'page_setup' => $this->pages->create(self::FORM_ACTIONS, $this->pages->trans('action.delete')),
            'form' => $form->createView(),
            'item' => $ruleset->getName(),
            'message' => $this->pages->trans('drehzettel.ruleset.delete_warning'),
            'back' => $this->generateUrl('drehzettel_ruleset_list'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function store(FilmRuleset $ruleset, array $data, FormInterface $form): bool
    {
        try {
            $ruleset->setRules(RulesetCodec::toArray(RulesetFormMapper::fromForm($data)));
        } catch (\DomainException|\ValueError $e) {
            $this->logException($e);
            $form->addError(new FormError($this->pages->trans('drehzettel.rules.invalid')));

            return false;
        }

        $this->custom->save($ruleset);
        $this->flashSuccess('action.update.success');

        return true;
    }

    private function renderEditor(FormInterface $form, string $title, ?string $context, ?string $delete = null): Response
    {
        $back = $this->generateUrl('drehzettel_ruleset_list');

        return $this->render('@Drehzettel/drehzettel/ruleset_form.html.twig', [
            'page_setup' => $this->pages->create(self::FORM_ACTIONS, $title, ['back' => $back, 'delete' => $delete]),
            'form' => $form->createView(),
            'title' => $title,
            'context' => [$context],
            'back' => $back,
        ]);
    }

    private function find(int $id): FilmRuleset
    {
        $ruleset = $this->custom->find($id);
        if ($ruleset === null) {
            throw $this->createNotFoundException('Unknown ruleset.');
        }

        return $ruleset;
    }
}
