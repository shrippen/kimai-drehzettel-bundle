<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Utils\PageSetup;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Kimai page header for every Drehzettel page: title "Drehzettel · <part>",
 * the action name the PageActionsEvent subscribers listen to, and the help link.
 *
 *   create('drehzettel_week', 'KW 21') -> "Drehzettel · KW 21", actions.drehzettel_week
 */
class PageSetups
{
    public const HELP_URL = 'https://github.com/shrippen/kimai-drehzettel-bundle#readme';

    private const TITLE_KEY = 'drehzettel.menu';
    private const SEPARATOR = ' · ';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param string $action PageActionsEvent name, also used when the page has no actions
     *                       (Kimai renders the page header only with an action name)
     * @param string|null $part translated title suffix, null for the bare "Drehzettel"
     * @param array<string, mixed> $payload passed to the actions subscriber
     */
    public function create(string $action, ?string $part = null, array $payload = []): PageSetup
    {
        $title = $this->translator->trans(self::TITLE_KEY);
        if ($part !== null && $part !== '') {
            $title .= self::SEPARATOR . $part;
        }

        $page = new PageSetup($title);
        $page->setActionName($action);
        $page->setActionPayload($payload);
        $page->setHelp(self::HELP_URL);

        return $page;
    }

    // Translates a plugin key for a title part.
    public function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
