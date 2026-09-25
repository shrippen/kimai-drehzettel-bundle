<?php

namespace KimaiPlugin\DrehzettelBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Success response of a form that may run in Kimai's modal (kimai-plugin-ui GUIDELINES 3.6, README
 * "Modal-Formulare mit Ergebnis-Hinweis"). A plain 302 would be followed in the background by the modal
 * and consume kpu_result flashes; so in the modal:
 * - keepUrl = false: 201 + x-modal-redirect, Kimai loads <route> completely;
 * - keepUrl = true : empty 200, Kimai closes the modal and fires the form's data-form-event
 *                    (must be 'kpu.reload', kit.js then reloads the current page with its week/filter).
 * Without the modal (page, no JS) it is a normal redirect.
 */
trait KpuFormSuccessTrait
{
    private function kpuFormSuccess(Request $request, string $route, array $parameters = [], bool $keepUrl = false): Response
    {
        $modal = str_contains(strtolower((string) $request->headers->get('X-Requested-With')), 'kimai-modal');
        if (!$modal) {
            return $this->redirectToRoute($route, $parameters);
        }

        return $keepUrl ? new Response('') : $this->redirectToRouteAfterCreate($route, $parameters);
    }
}
