<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetFormMapper;
use KimaiPlugin\DrehzettelBundle\Entity\FilmRuleset;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;
use KimaiPlugin\DrehzettelBundle\Service\RulesetCatalog;
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

    public function __construct(
        private readonly FilmRulesetRepository $custom,
        private readonly RulesetCatalog $catalog,
    ) {
    }

    #[Route(path: '/', name: 'drehzettel_ruleset_list', methods: ['GET'])]
    public function list(): Response
    {
        $builtin = [RulesetCatalog::TV_FFS_2024, RulesetCatalog::QUARTER_HOUR];

        return $this->render('@Drehzettel/drehzettel/ruleset_list.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'builtin' => array_map(fn (string $key): array => ['key' => $key, 'name' => $this->catalog->get($key)->name], $builtin),
            'custom' => $this->custom->findAllSorted(),
        ]);
    }

    #[Route(path: '/new/{from}', name: 'drehzettel_ruleset_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $from): Response
    {
        $base = $this->catalog->get($from);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $name = trim((string) $request->request->get('name'));
            if ($name === '' || $this->custom->findByName($name) !== null) {
                $this->flashError('action.update.error');
            } else {
                try {
                    $data = $request->request->all();
                    $data['name'] = $name;
                    $rules = RulesetFormMapper::fromForm($data);

                    $ruleset = new FilmRuleset();
                    $ruleset->setName($name);
                    $ruleset->setRules(RulesetCodec::toArray($rules));
                    $this->custom->save($ruleset);
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('drehzettel_ruleset_list');
                } catch (\Throwable $e) {
                    $this->flashError('action.update.error', $e->getMessage());
                }
            }
        }

        return $this->render('@Drehzettel/drehzettel/ruleset_form.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'engagement' => null,
            'form' => RulesetFormMapper::toForm($base),
            'target' => 'drehzettel_ruleset_new',
            'target_params' => ['from' => $from],
            'suggest_name' => $base->name . ' copy',
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'drehzettel_ruleset_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $ruleset = $this->find($id);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            try {
                $data = $request->request->all();
                $data['name'] = $ruleset->getName();
                $rules = RulesetFormMapper::fromForm($data);
                $ruleset->setRules(RulesetCodec::toArray($rules));
                $this->custom->save($ruleset);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('drehzettel_ruleset_list');
            } catch (\Throwable $e) {
                $this->flashError('action.update.error', $e->getMessage());
            }
        }

        return $this->render('@Drehzettel/drehzettel/ruleset_form.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'engagement' => null,
            'form' => RulesetFormMapper::toForm(RulesetCodec::fromArray($ruleset->getRules())),
            'target' => 'drehzettel_ruleset_edit',
            'target_params' => ['id' => $id],
            'ruleset_name' => $ruleset->getName(),
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'drehzettel_ruleset_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $ruleset = $this->find($id);
        if ($this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            $this->custom->remove($ruleset);
            $this->flashSuccess('action.delete.success');
        }

        return $this->redirectToRoute('drehzettel_ruleset_list');
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
