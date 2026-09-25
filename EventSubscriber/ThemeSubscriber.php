<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Two small, sitewide additions via Kimai's own theme extension points
 * (App\Event\ThemeEvent - see base.html.twig's "trigger()" calls), requested
 * 2026-09-23:
 *
 * 1. CSS that groups the five Drehzettel fields TimesheetFormExtension adds
 *    to Kimai's timesheet form into one amber-tinted block with a clear
 *    toggle (form concept A, https://claude.ai/artifact/CB2kY9aB66GbHzLTVnTZjS),
 *    plus the "bg-drehzettel" cell color for the /contract page markers
 *    added by ContractSubscriber. Colors only through Tabler variables, so
 *    dark mode follows Kimai.
 * 2. JS that calls the plugin's own API (research/api-external-clients.md)
 *    when the project field changes on a timesheet form, so picking a
 *    project with an active engagement gives immediate visual feedback -
 *    previously there was none, since TimesheetFormExtension only decides
 *    at the form's initial build time (see that class's own doc comment).
 *    URL and text come as data attributes of the script tag, translated
 *    for the current user.
 *
 * Both are inert on every other page: the CSS only styles classes/selectors
 * that no other page uses, and the JS no-ops unless a "*_project" select is
 * actually present.
 */
class ThemeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ThemeEvent::STYLESHEET => 'onStylesheet',
            ThemeEvent::JAVASCRIPT => 'onJavascript',
        ];
    }

    public function onStylesheet(ThemeEvent $event): void
    {
        $event->addContent(<<<'HTML'
            <style>
                .dz-form-row{background:var(--tblr-yellow-lt);border-left:3px solid var(--tblr-yellow);padding-left:.75rem!important;margin-left:-.75rem;}
                .dz-form-row-first{padding-top:.75rem!important;border-radius:var(--tblr-border-radius-lg) var(--tblr-border-radius-lg) 0 0;margin-top:.5rem;}
                .dz-form-row-last{padding-bottom:.75rem!important;border-radius:0 0 var(--tblr-border-radius-lg) var(--tblr-border-radius-lg);}
                .bg-drehzettel{background-color:var(--tblr-yellow-lt);--tblr-table-bg:var(--tblr-yellow-lt);}
            </style>
            HTML);
    }

    // The API URL comes from the router, so Kimai under a sub path (/kimai/api/...) works too.
    public function onJavascript(ThemeEvent $event): void
    {
        $url = htmlspecialchars($this->urls->generate('drehzettel_api_engagement_status'), ENT_QUOTES);
        $text = htmlspecialchars($this->translator->trans('drehzettel.form.detected'), ENT_QUOTES);

        $event->addContent(<<<HTML
            <script data-dz-status-url="{$url}" data-dz-detected="{$text}">
            HTML . <<<'HTML'
            (function (config) {
                // Delegated on document: survives the timesheet edit form being re-inserted by
                // Kimai's AJAX modal loader. Only reacts to a project select's own change event.
                // Date is intentionally not read here - this is a same-day hint, the actual
                // save-time check in TimesheetFormExtension uses the entry's real date.
                function existingBanner(row) {
                    const next = row.nextElementSibling;
                    return (next && next.classList.contains('dz-detect-banner')) ? next : null;
                }

                function checkProject(select) {
                    const row = select.closest('.row, .mb-3');
                    if (!row) {
                        return;
                    }
                    if (!select.value) {
                        const old = existingBanner(row);
                        if (old) {
                            old.remove();
                        }
                        return;
                    }
                    fetch(config.dzStatusUrl + '?project=' + encodeURIComponent(select.value), {
                        headers: {'Accept': 'application/json'},
                        credentials: 'same-origin'
                    })
                        .then((response) => response.ok ? response.json() : null)
                        .then((data) => {
                            let banner = existingBanner(row);
                            if (!data || !data.active) {
                                if (banner) {
                                    banner.remove();
                                }
                                return;
                            }
                            if (!banner) {
                                banner = document.createElement('div');
                                banner.className = 'alert alert-warning dz-detect-banner';
                                banner.setAttribute('role', 'status');
                                row.insertAdjacentElement('afterend', banner);
                            }
                            banner.textContent = config.dzDetected.replace('%ruleset%', data.rulesetName || '');
                        })
                        .catch(() => { /* a hint only: without it the form works as before */ });
                }

                document.addEventListener('kimai.initialized', function () {
                    document.addEventListener('change', function (event) {
                        const target = event.target;
                        if (target && target.id && /_project$/.test(target.id) && target.tagName === 'SELECT') {
                            checkProject(target);
                        }
                    });
                });
            })(document.currentScript.dataset);
            </script>
            HTML);
    }
}
