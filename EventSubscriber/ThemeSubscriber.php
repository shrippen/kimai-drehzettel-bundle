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
 *    added by ContractSubscriber, plus (2026-09-24) ".dz-hidden" for the
 *    fields TimesheetFormExtension now renders but starts collapsed on a
 *    brand-new entry.
 * 2. JS that calls the plugin's own API (research/api-external-clients.md)
 *    when the project *or activity* field changes on a timesheet form, so
 *    picking a project(+activity) with an active engagement gives immediate
 *    visual feedback (the amber banner) and, since 2026-09-24, actually
 *    reveals TimesheetFormExtension's five fields live - they used to only
 *    appear after saving and reopening the entry, since that class only
 *    decided once, at the form's initial build time (see that class's own
 *    doc comment). An engagement may also restrict itself to specific
 *    activities (Entity\Engagement::activityIds) - e.g. excluding a private
 *    "Anfahrt"/commute activity from the same project - so both fields are
 *    read and sent, not just the project.
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
                .dz-hidden{display:none!important;}
            </style>
            HTML);
    }

    public function onJavascript(ThemeEvent $event): void
    {
        $event->addContent(<<<'HTML'
            <script>
            (function () {
                var texts = {
                    de: {active: 'Drehtag erkannt — Regelwerk „%ruleset%“. Die Drehzettel-Felder sind unten sichtbar.'},
                    en: {active: 'Film day detected — ruleset "%ruleset%". The Drehzettel fields are shown below.'}
                };
                var lang = (document.documentElement.lang || 'en').slice(0, 2);
                var t = texts[lang] || texts.en;

                function existingBanner(row) {
                    var next = row.nextElementSibling;
                    return (next && next.classList.contains('dz-detect-banner')) ? next : null;
                }

                function fieldRows(form) {
                    return form.querySelectorAll('.dz-form-row, .dz-form-row-first, .dz-form-row-last');
                }

                // TimesheetFormExtension always renders these five fields now (hidden via
                // dz-hidden when no engagement was known at form-build time), so a live
                // match here only needs to reveal them - no fields to create or remove.
                function setFieldsVisible(form, visible) {
                    var rows = fieldRows(form);
                    for (var i = 0; i < rows.length; i++) {
                        rows[i].classList.toggle('dz-hidden', !visible);
                    }
                }

                // Reacts to the project *or* activity select changing: an engagement may
                // restrict itself to specific activities (Entity\Engagement::activityIds),
                // e.g. excluding a private "Anfahrt"/commute activity from the same
                // project, so a match depends on both, not just the project.
                function checkEngagement(select) {
                    var form = select.closest('form');
                    if (!form) { return; }
                    var projectSelect = form.querySelector('select[id$="_project"]');
                    if (!projectSelect) { return; }
                    var activitySelect = form.querySelector('select[id$="_activity"]');
                    var row = projectSelect.closest('.row, .mb-3');
                    if (!row) { return; }

                    var projectId = projectSelect.value;
                    if (!projectId) {
                        var toRemove = existingBanner(row);
                        if (toRemove) { toRemove.remove(); }
                        setFieldsVisible(form, false);
                        return;
                    }
                    var activityId = activitySelect ? activitySelect.value : '';
                    var endpoint = '/api/drehzettel/v1/engagement-status?project=' + encodeURIComponent(projectId);
                    if (activityId) {
                        endpoint += '&activity=' + encodeURIComponent(activityId);
                    }
                    fetch(endpoint, {
                        headers: {'Accept': 'application/json'},
                        credentials: 'same-origin'
                    })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            var el = existingBanner(row);
                            if (!data || !data.active) {
                                if (el) { el.remove(); }
                                setFieldsVisible(form, false);
                                return;
                            }
                            if (!el) {
                                el = document.createElement('div');
                                el.className = 'dz-detect-banner';
                                el.innerHTML = '<i class="fas fa-clapperboard"></i><span></span>';
                                row.insertAdjacentElement('afterend', el);
                            }
                            el.querySelector('span').textContent = t.active.replace('%ruleset%', data.rulesetName || '');
                            setFieldsVisible(form, true);
                        })
                        .catch(function () { /* feedback is a convenience, ignore network errors - leave fields/banner as-is */ });
                }

                // Delegated on document: survives the timesheet edit form being
                // re-inserted by Kimai's AJAX modal loader (KimaiAjaxModalForm).
                // Only reacts to the project/activity fields' own change event, never
                // fires on unrelated pages. Date is intentionally not read here - this
                // is a same-day hint, the actual save-time check in
                // TimesheetFormExtension uses the entry's real date.
                document.addEventListener('change', function (event) {
                    var target = event.target;
                    if (target && target.id && /_(project|activity)$/.test(target.id) && target.tagName === 'SELECT') {
                        checkEngagement(target);
                    }
                });
            })();
            </script>
            HTML);
    }
}
