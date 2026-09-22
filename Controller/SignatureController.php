<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\DrehzettelBundle\Service\SignatureService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel/signature')]
#[IsGranted('drehzettel')]
class SignatureController extends AbstractController
{
    private const CSRF_ID = 'drehzettel_signature';

    public function __construct(private readonly SignatureService $signatures)
    {
    }

    #[Route(path: '/', name: 'drehzettel_signature', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();

        if ($request->isMethod('POST') && $this->isCsrfTokenValid(self::CSRF_ID, $request->request->get('_token'))) {
            if ($request->request->has('remove')) {
                $this->signatures->delete($user);
                $this->flashSuccess('action.delete.success');
            } else {
                $file = $request->files->get('signature');
                if ($file === null) {
                    $this->flashError('action.update.error');
                } else {
                    try {
                        $this->signatures->save($user, (string) file_get_contents($file->getPathname()));
                        $this->flashSuccess('action.update.success');
                    } catch (\InvalidArgumentException $e) {
                        $this->flashError('action.update.error', $e->getMessage());
                    }
                }
            }

            return $this->redirectToRoute('drehzettel_signature');
        }

        return $this->render('@Drehzettel/drehzettel/signature.html.twig', [
            'page_setup' => new PageSetup('drehzettel.menu'),
            'has_signature' => $this->signatures->has($user),
        ]);
    }
}
