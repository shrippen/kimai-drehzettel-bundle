<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\DrehzettelBundle\Repository\EngagementRepository;
use KimaiPlugin\DrehzettelBundle\Service\EngagementAccess;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel')]
#[IsGranted('drehzettel')]
class OverviewController extends AbstractController
{
    public function __construct(
        private readonly EngagementRepository $engagements,
        private readonly EngagementAccess $access,
        private readonly PageSetups $pages,
    ) {
    }

    #[Route(path: '/', name: 'drehzettel_overview', methods: ['GET'])]
    public function index(): Response
    {
        $manages = $this->access->canManage();
        $engagements = $manages ? $this->engagements->findAllSorted() : $this->engagements->findForUser($this->getUser());

        return $this->render('@Drehzettel/drehzettel/overview.html.twig', [
            'page_setup' => $this->pages->create('drehzettel_overview'),
            'engagements' => $engagements,
            'manages' => $manages,
            'today' => new \DateTimeImmutable('today'),
        ]);
    }
}
