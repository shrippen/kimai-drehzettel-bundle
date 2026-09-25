<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\DrehzettelBundle\Form\SignatureType;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Service\SignatureService;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/drehzettel/signature')]
#[IsGranted('drehzettel')]
class SignatureController extends AbstractController
{
    private const CSRF_ID = 'drehzettel_signature';

    public function __construct(
        private readonly SignatureService $signatures,
        private readonly PageSetups $pages,
    ) {
    }

    #[Route(path: '/', name: 'drehzettel_signature', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(SignatureType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get(SignatureType::FIELD)->getData();
            try {
                $this->signatures->save($user, (string) file_get_contents($file->getPathname()));
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('drehzettel_signature');
            } catch (\InvalidArgumentException) {
                $form->get(SignatureType::FIELD)->addError(new FormError($this->pages->trans('drehzettel.signature.invalid_content')));
            }
        }

        $stored = $this->signatures->dataUri($user);

        return $this->render('@Drehzettel/drehzettel/signature.html.twig', [
            'page_setup' => $this->pages->create('drehzettel_form', $this->pages->trans('drehzettel.signature'), [
                'back' => $this->generateUrl('drehzettel_overview'),
                'delete' => $stored === null ? null : $this->generateUrl('drehzettel_signature_delete'),
            ]),
            'form' => $form->createView(),
            'signature' => $stored,
        ]);
    }

    // Kimai delete confirmation (modal or page), POST removes the signature.
    #[Route(path: '/delete', name: 'drehzettel_signature_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request): Response
    {
        $form = $this->createFormBuilder(null, ['csrf_token_id' => self::CSRF_ID, 'attr' => ['data-form-event' => 'kimai.drehzettelSignatureDelete']])
            ->setAction($this->generateUrl('drehzettel_signature_delete'))
            ->setMethod('POST')
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->signatures->delete($this->getUser());
            $this->flashSuccess('action.delete.success');

            return $this->redirectToRoute('drehzettel_signature');
        }

        return $this->render('@Drehzettel/drehzettel/delete.html.twig', [
            'page_setup' => $this->pages->create('drehzettel_form', $this->pages->trans('drehzettel.signature')),
            'form' => $form->createView(),
            'item' => $this->pages->trans('drehzettel.signature'),
            'message' => $this->pages->trans('drehzettel.signature.delete_warning'),
            'back' => $this->generateUrl('drehzettel_signature'),
        ]);
    }
}
