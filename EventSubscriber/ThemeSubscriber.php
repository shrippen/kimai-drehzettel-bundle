<?php

namespace KimaiPlugin\DrehzettelBundle\EventSubscriber;

use App\Event\ThemeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Two small, sitewide additions via Kimai's own theme extension points
 * (App\Event\ThemeEvent - see base.html.twig's "trigger()" calls), requested
 * 2026-09-23:
 *
 * 1. The kit CSS (kpu- classes only, inert elsewhere), so Kimai's own pages
 *    can use the kit markers: the Drehzettel fields TimesheetFormExtension
 *    adds to the timesheet form are wrapped in one kit field group (form
 *    concept A, https://claude.ai/artifact/CB2kY9aB66GbHzLTVnTZjS), and the
 *    film days ContractSubscriber marks on /contract become kit calendar
 *    days of kind "entity" (both purple with Knust, Kante's entity colour).
 *    Plus (2026-09-24) ".dz-hidden" for the fields TimesheetFormExtension
 *    renders but starts collapsed on a brand-new entry.
 * 2. JS that calls the plugin's own API (research/api-external-clients.md)
 *    when the project *or activity* field changes on a timesheet form, so
 *    picking a project(+activity) with an active engagement gives immediate
 *    visual feedback (an info alert) and, since 2026-09-24, actually
 *    reveals TimesheetFormExtension's five fields live - they used to only
 *    appear after saving and reopening the entry, since that class only
 *    decided once, at the form's initial build time (see that class's own
 *    doc comment). An engagement may also restrict itself to specific
 *    activities (Entity\Engagement::activityIds) - e.g. excluding a private
 *    "Anfahrt"/commute activity from the same project - so both fields are
 *    read and sent, not just the project. URL and both texts (shown vs.
 *    after-save) come as data attributes of the script tag, translated for
 *    the current user.
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
        private readonly Environment $twig,
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
        $event->addContent($this->twig->render('@Drehzettel/_kit/assets.html.twig', ['kpu_part' => 'css']));
        $event->addContent(<<<'HTML'
            <style>
                .dz-hidden{display:none!important;}
            </style>
            HTML);
    }

    // The API URL comes from the router, so Kimai under a sub path (/kimai/api/...) works too.
    public function onJavascript(ThemeEvent $event): void
    {
        $url = htmlspecialchars($this->urls->generate('drehzettel_api_engagement_status'), ENT_QUOTES);
        $shown = htmlspecialchars($this->translator->trans('drehzettel.form.detected_shown'), ENT_QUOTES);
        $afterSave = htmlspecialchars($this->translator->trans('drehzettel.form.detected'), ENT_QUOTES);
        $group = htmlspecialchars($this->translator->trans('drehzettel.menu'), ENT_QUOTES);

        $event->addContent(<<<HTML
            <script data-dz-status-url="{$url}" data-dz-detected-shown="{$shown}" data-dz-detected="{$afterSave}" data-dz-group="{$group}">
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
                    var group = form.querySelector('.dz-field-group');
                    if (group) { group.classList.toggle('dz-hidden', !visible); }
                    // Kimai's native break field stays in the form for new entries (it's only
                    // removed server-side when the fields are shown at build time), so swap it
                    // out visually - two "Pause" inputs at once is what concept A avoids. Only
                    // its own form-row wrapper (.mb-3), never a wider .row that could hold
                    // begin/end too. Data stays independent either way (no sync).
                    if (rows.length === 0) { return; }
                    var nativeBreak = form.querySelector('[id$="_break"]');
                    var breakRow = nativeBreak ? nativeBreak.closest('.mb-3') : null;
                    if (breakRow && !breakRow.classList.contains('dz-form-row')) {
                        breakRow.classList.toggle('dz-hidden', visible);
                    }
                }

                // Reacts to the project *or* activity select changing: an engagement may
                // restrict itself to specific activities (Entity\Engagement::activityIds),
                // e.g. excluding a private "Anfahrt"/commute activity from the same
                // project, so a match depends on both, not just the project.
                // Changing the project makes Kimai reload the activity select and fire a
                // second change event, so two checks would race. Coalesce bursts per form,
                // and tag each request so only the latest answer for that form is applied.
                function scheduleCheck(select) {
                    var form = select.closest('form');
                    if (!form) { return; }
                    if (form._dzTimer) { clearTimeout(form._dzTimer); }
                    form._dzTimer = setTimeout(function () {
                        form._dzTimer = null;
                        checkEngagement(form);
                    }, 150);
                }

                function checkEngagement(form) {
                    var seq = (form._dzSeq || 0) + 1;
                    form._dzSeq = seq;
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
                    var endpoint = config.dzStatusUrl + '?project=' + encodeURIComponent(projectId);
                    if (activityId) {
                        endpoint += '&activity=' + encodeURIComponent(activityId);
                    }
                    fetch(endpoint, {
                        headers: {'Accept': 'application/json'},
                        credentials: 'same-origin'
                    })
                        .then(function (response) { return response.ok ? response.json() : null; })
                        .then(function (data) {
                            if (form._dzSeq !== seq) { return; }
                            var banner = existingBanner(row);
                            if (!data || !data.active) {
                                if (banner) { banner.remove(); }
                                setFieldsVisible(form, false);
                                return;
                            }
                            if (!banner) {
                                banner = document.createElement('div');
                                banner.className = 'alert alert-info dz-detect-banner';
                                banner.setAttribute('role', 'status');
                                row.insertAdjacentElement('afterend', banner);
                            }
                            var text = fieldRows(form).length > 0 ? config.dzDetectedShown : config.dzDetected;
                            banner.textContent = text.replace('%ruleset%', data.rulesetName || '');
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
                // Kit markers on Kimai's own markup, which the plugin cannot template:
                // the Drehzettel rows go into one kit field group (hidden with its rows),
                // ContractSubscriber's film days (td.bg-drehzettel) become kit "entity" days.
                // Rerun on DOM changes: Kimai's AJAX modal inserts the form later.
                function applyMarkers() {
                    var first = document.querySelectorAll('.dz-form-row-first');
                    for (var i = 0; i < first.length; i++) {
                        if (first[i].parentElement.classList.contains('dz-field-group')) { continue; }
                        var group = document.createElement('fieldset');
                        group.className = 'kpu-field-group dz-field-group';
                        group.classList.toggle('dz-hidden', first[i].classList.contains('dz-hidden'));
                        var legend = document.createElement('legend');
                        legend.className = 'kpu-field-group-label';
                        legend.textContent = config.dzGroup;
                        group.appendChild(legend);
                        first[i].parentElement.insertBefore(group, first[i]);
                        var row = first[i];
                        while (row && row.classList.contains('dz-form-row')) {
                            var next = row.nextElementSibling;
                            group.appendChild(row);
                            row = next;
                        }
                    }
                    var days = document.querySelectorAll('td.bg-drehzettel:not(.kpu-day)');
                    for (var j = 0; j < days.length; j++) {
                        days[j].classList.add('kpu-day');
                        days[j].setAttribute('data-kpu-day', 'entity');
                    }
                }

                document.addEventListener('kimai.initialized', function () {
                    applyMarkers();
                    new MutationObserver(applyMarkers).observe(document.body, {childList: true, subtree: true});
                    document.addEventListener('change', function (event) {
                        var target = event.target;
                        if (target && target.id && /_(project|activity)$/.test(target.id) && target.tagName === 'SELECT') {
                            scheduleCheck(target);
                        }
                    });
                });
            })(document.currentScript.dataset);
            </script>
            HTML);
    }
}
