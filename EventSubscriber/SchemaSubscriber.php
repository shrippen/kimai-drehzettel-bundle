<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use KimaiPlugin\DrehzettelBundle\Domain\ApiError;
use KimaiPlugin\DrehzettelBundle\Service\PageSetups;
use KimaiPlugin\DrehzettelBundle\Service\SchemaStatus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Pending plugin migrations: every Drehzettel route answers with the install
 * hint instead of a 500 from a missing table.
 *
 *   drehzettel_week     -> page "run bin/console kimai:bundle:drehzettel:install" (503)
 *   drehzettel_api_*    -> 503 {"error": "...", "code": "update_pending"}
 *   drehzettel_api_ping -> unchanged, clients detect the plugin with it
 */
final class SchemaSubscriber implements EventSubscriberInterface
{
    private const ROUTES = 'drehzettel_';
    private const API_ROUTES = 'drehzettel_api_';
    private const PING_ROUTE = 'drehzettel_api_ping';
    private const ACTION = 'drehzettel_update_pending';

    public function __construct(
        private readonly SchemaStatus $schema,
        private readonly PageSetups $pages,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route');
        if (!$event->isMainRequest() || !str_starts_with($route, self::ROUTES) || $route === self::PING_ROUTE) {
            return;
        }

        if ($this->schema->isCurrent()) {
            return;
        }

        $api = str_starts_with($route, self::API_ROUTES);
        $event->setController(fn (): Response => $api ? $this->apiError() : $this->page());
    }

    private function apiError(): JsonResponse
    {
        $error = ApiError::updatePending('Drehzettel database update pending: run ' . SchemaStatus::INSTALL_COMMAND . '.');

        return new JsonResponse($error->body(), $error->status);
    }

    private function page(): Response
    {
        $html = $this->twig->render('@Drehzettel/drehzettel/update_pending.html.twig', [
            'page_setup' => $this->pages->create(self::ACTION),
            'command' => SchemaStatus::INSTALL_COMMAND,
        ]);

        return new Response($html, ApiError::HTTP_SERVICE_UNAVAILABLE);
    }
}
