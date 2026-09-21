<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

use KimaiPlugin\DrehzettelBundle\Enum\PdfOption;

/**
 * Which columns and sections the PDF contains. Stored as a list of keys.
 * Pay and under-time are off by default: the production office asks for
 * hours, not for the crew member's gage.
 */
final class PdfOptions
{
    /** @var list<PdfOption> */
    private readonly array $enabled;

    /**
     * @param list<PdfOption> $enabled
     */
    public function __construct(array $enabled)
    {
        $this->enabled = array_values(array_unique($enabled, SORT_REGULAR));
    }

    public static function defaults(): self
    {
        return new self([
            PdfOption::BREAK,
            PdfOption::TIERS,
            PdfOption::NIGHT,
            PdfOption::CATERING,
            PdfOption::DAY_TYPE,
            PdfOption::WEEKLY_OVERTIME,
            PdfOption::NOTES,
            PdfOption::SIGNATURE_LINES,
            PdfOption::SIGNATURE_IMAGE,
            PdfOption::ROUNDING_NOTE,
        ]);
    }

    /**
     * @param list<string>|null $keys unknown keys are ignored, null gives the defaults
     */
    public static function fromKeys(?array $keys): self
    {
        if ($keys === null) {
            return self::defaults();
        }

        $enabled = [];
        foreach ($keys as $key) {
            $option = PdfOption::tryFrom((string) $key);
            if ($option !== null) {
                $enabled[] = $option;
            }
        }

        return new self($enabled);
    }

    public function has(PdfOption $option): bool
    {
        return in_array($option, $this->enabled, true);
    }

    /**
     * @return list<string>
     */
    public function toKeys(): array
    {
        return array_map(static fn (PdfOption $o): string => $o->value, $this->enabled);
    }
}
