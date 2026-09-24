<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Two small, sitewide additions via Kimai's own theme extension points
 * (App\Event\ThemeEvent - see base.html.twig's "trigger()" calls), requested
 * 2026-09-23:
 *
 * 1. CSS that groups the five Drehzettel fields TimesheetFormExtension adds
 *    to Kimai's timesheet form into one amber-tinted block with a clear
 *    toggle (form concept A, https://claude.ai/artifact/CB2kY9aB66GbHzLTVnTZjS),
 *    plus the "bg-drehzettel" cell color for the /contract page markers
 *    added by ContractSubscriber.
 * 2. JS that calls the plugin's own API (research/api-external-clients.md)
 *    when the project field changes on a timesheet form, so picking a
 *    project with an active engagement gives immediate visual feedback -
 *    previously there was none, since TimesheetFormExtension only decides
 *    at the form's initial build time (see that class's own doc comment).
 *
 * Both are inert on every other page: the CSS only styles classes/selectors
 * that no other page uses, and the JS no-ops unless a "*_project" select is
 * actually present.
 */
class ThemeSubscriber implements EventSubscriberInterface
{
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
                .dz-form-row-first{padding-top:.75rem!important;border-radius:var(--tblr-border-radius-lg,10px) var(--tblr-border-radius-lg,10px) 0 0;margin-top:.5rem;}
                .dz-form-row-last{padding-bottom:.75rem!important;border-radius:0 0 var(--tblr-border-radius-lg,10px) var(--tblr-border-radius-lg,10px);}
                .bg-drehzettel{background-color:var(--tblr-yellow-lt);--tblr-table-bg:var(--tblr-yellow-lt);}
                .dz-detect-banner{display:flex;align-items:center;gap:.5rem;background:var(--tblr-yellow-lt);border-left:3px solid var(--tblr-yellow);border-radius:var(--tblr-border-radius,6px);padding:.5rem .75rem;margin-bottom:1rem;font-size:.85rem;}
                .dz-detect-banner i{color:var(--tblr-yellow);}
            </style>
            HTML);
    }

    public function onJavascript(ThemeEvent $event): void
    {
        $event->addContent(<<<'HTML'
            <script>
            (function () {
                var texts = {
                    de: {active: 'Drehtag erkannt — Regelwerk „%ruleset%“. Die Drehzettel-Felder erscheinen nach dem Speichern beim Bearbeiten dieses Eintrags.'},
                    en: {active: 'Film day detected — ruleset "%ruleset%". The Drehzettel fields appear after saving, when you edit this entry.'}
                };
                var lang = (document.documentElement.lang || 'en').slice(0, 2);
                var t = texts[lang] || texts.en;

                function existingBanner(row) {
                    var next = row.nextElementSibling;
                    return (next && next.classList.contains('dz-detect-banner')) ? next : null;
                }

                function checkProject(select) {
                    var row = select.closest('.row, .mb-3');
                    if (!row) { return; }
                    var projectId = select.value;
                    if (!projectId) {
                        var toRemove = existingBanner(row);
                        if (toRemove) { toRemove.remove(); }
                        return;
                    }
                    fetch('/api/drehzettel/v1/engagement-status?project=' + encodeURIComponent(projectId), {
                        headers: {'Accept': 'application/json'},
                        credentials: 'same-origin'
                    })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            var el = existingBanner(row);
                            if (!data || !data.active) {
                                if (el) { el.remove(); }
                                return;
                            }
                            if (!el) {
                                el = document.createElement('div');
                                el.className = 'dz-detect-banner';
                                el.innerHTML = '<i class="fas fa-clapperboard"></i><span></span>';
                                row.insertAdjacentElement('afterend', el);
                            }
                            el.querySelector('span').textContent = t.active.replace('%ruleset%', data.rulesetName || '');
                        })
                        .catch(function () { /* feedback is a convenience, ignore network errors */ });
                }

                // Delegated on document: survives the timesheet edit form being
                // re-inserted by Kimai's AJAX modal loader (KimaiAjaxModalForm).
                // Only reacts to a project field's own change event, never fires
                // on unrelated pages. Date is intentionally not read here - this
                // is a same-day hint, the actual save-time check in
                // TimesheetFormExtension uses the entry's real date.
                document.addEventListener('change', function (event) {
                    var target = event.target;
                    if (target && target.id && /_project$/.test(target.id) && target.tagName === 'SELECT') {
                        checkProject(target);
                    }
                });
            })();
            </script>
            HTML);
    }
}
