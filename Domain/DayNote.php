<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * The note of a film day is the description of its Kimai entries - one field,
 * not a second one next to it. Several entries share the note:
 *
 *   read    07:00 "Regen", 14:00 "Umbau"   -> "Regen\nUmbau"
 *   assign  "Nachdreh"                      -> 07:00 "Nachdreh", 14:00 null
 *
 * Descriptions are in begin order.
 */
final class DayNote
{
    public const MAX_LENGTH = 10000;

    private const SEPARATOR = "\n";

    /**
     * @param list<?string> $descriptions
     */
    public static function join(array $descriptions): ?string
    {
        $parts = array_values(array_filter(array_map(static fn (?string $d): string => self::lines($d), $descriptions), static fn (string $d): bool => $d !== ''));

        return $parts === [] ? null : implode(self::SEPARATOR, $parts);
    }

    /**
     * New descriptions for a changed note: the first entry holds it, the others are cleared.
     * Null when the note is unchanged, so nothing is written.
     *
     * @param list<?string> $descriptions
     * @return list<?string>|null
     */
    public static function assign(array $descriptions, ?string $note): ?array
    {
        $note = self::clean($note);
        if ($descriptions === [] || $note === self::join($descriptions)) {
            return null;
        }

        $assigned = array_fill(0, \count($descriptions), null);
        $assigned[0] = $note;

        return $assigned;
    }

    // Blank means no note.
    public static function clean(?string $note): ?string
    {
        $text = self::lines($note);

        return $text === '' ? null : mb_substr($text, 0, self::MAX_LENGTH);
    }

    // Trimmed, line breaks as \n: a browser sends a textarea's as \r\n.
    private static function lines(?string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
    }
}
