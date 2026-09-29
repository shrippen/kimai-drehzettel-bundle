<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * Subject and text of the timesheet mail with {placeholders}:
 *
 *   "Stundenzettel KW {week} – {project}"  ->  "Stundenzettel KW 39 – Graufeld"
 *
 * Unknown placeholders stay as typed.
 */
final class MailTemplate
{
    public const MAX_SUBJECT_LENGTH = 255;
    public const MAX_BODY_LENGTH = 5000;

    public const PLACEHOLDERS = ['name', 'project', 'customer', 'role', 'period', 'from', 'to', 'week', 'month', 'year'];

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {
    }

    /**
     * @param array<string, string> $values by placeholder name, without braces
     */
    public function render(array $values): self
    {
        $pairs = [];
        foreach (self::PLACEHOLDERS as $name) {
            $pairs['{' . $name . '}'] = $values[$name] ?? '';
        }

        return new self(strtr($this->subject, $pairs), strtr($this->body, $pairs));
    }

    // "{name}, {project}" - for the help text of the editor.
    public static function placeholderList(): string
    {
        return implode(', ', array_map(static fn (string $name): string => '{' . $name . '}', self::PLACEHOLDERS));
    }
}
