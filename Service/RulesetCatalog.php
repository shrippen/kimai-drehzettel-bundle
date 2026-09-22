<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use KimaiPlugin\DrehzettelBundle\Domain\Ruleset;
use KimaiPlugin\DrehzettelBundle\Domain\RulesetCodec;
use KimaiPlugin\DrehzettelBundle\Domain\Rulesets;
use KimaiPlugin\DrehzettelBundle\Repository\FilmRulesetRepository;

/**
 * Ruleset templates: built-in presets first, then user-defined by name.
 */
class RulesetCatalog
{
    public const TV_FFS_2024 = 'tv_ffs_2024';
    public const QUARTER_HOUR = 'quarter_hour';

    public function __construct(private readonly FilmRulesetRepository $custom)
    {
    }

    /**
     * @return list<string> keys usable in get()
     */
    public function keys(): array
    {
        $keys = [self::TV_FFS_2024, self::QUARTER_HOUR];
        foreach ($this->custom->findAllSorted() as $ruleset) {
            $keys[] = $ruleset->getName();
        }

        return $keys;
    }

    public function get(string $key): Ruleset
    {
        if ($key === self::TV_FFS_2024) {
            return Rulesets::tvFfs2024();
        }
        if ($key === self::QUARTER_HOUR) {
            return Rulesets::quarterHour();
        }

        $custom = $this->custom->findByName($key);
        if ($custom === null) {
            throw new \InvalidArgumentException("Unknown ruleset '$key'.");
        }

        return RulesetCodec::fromArray($custom->getRules());
    }
}
