<?php

declare(strict_types=1);

namespace DrehzettelBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * The film day note was a second text next to Kimai's own description of the
 * entry. Moves each note into the description of the day's first film entry
 * (appended when the description holds other text), then drops the column:
 *
 *   description "Set 3", note "Regen"  ->  description "Set 3\nRegen"
 *   description "Regen", note "Regen"  ->  unchanged
 */
final class Version20260929120000 extends AbstractMigration
{
    private const DAY_TABLE = 'kimai2_ext_drehzettel_day';
    private const COLUMN = 'note';

    public function getDescription(): string
    {
        return 'DrehzettelBundle: film day note moves to the description of the Kimai entry';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable(self::DAY_TABLE)->hasColumn(self::COLUMN)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $days = $this->connection->fetchAllAssociative(
            "SELECT d.day_date, d.note, e.user_id, e.project_id, e.activity_ids
             FROM kimai2_ext_drehzettel_day d JOIN kimai2_ext_drehzettel_engagement e ON e.id = d.engagement_id
             WHERE d.note IS NOT NULL AND TRIM(d.note) <> ''"
        );
        foreach ($days as $day) {
            $this->moveNote($day);
        }

        $this->addSql(sprintf('ALTER TABLE %s DROP %s', self::DAY_TABLE, self::COLUMN));
    }

    public function down(Schema $schema): void
    {
        // The notes stay in the descriptions.
        $this->addSql(sprintf('ALTER TABLE %s ADD %s LONGTEXT DEFAULT NULL', self::DAY_TABLE, self::COLUMN));
    }

    /**
     * @param array<string, mixed> $day
     */
    private function moveNote(array $day): void
    {
        $note = trim((string) $day['note']);
        $activities = json_decode((string) $day['activity_ids'], true);
        $activities = \is_array($activities) ? array_map('intval', $activities) : [];

        $entries = $this->connection->fetchAllAssociative(
            'SELECT id, description, activity_id FROM kimai2_timesheet WHERE user = ? AND project_id = ? AND date_tz = ? ORDER BY start_time',
            [$day['user_id'], $day['project_id'], $day['day_date']]
        );
        foreach ($entries as $entry) {
            // Only film activities, as Engagement::appliesToActivity(); none listed = all.
            if ($activities !== [] && !\in_array((int) $entry['activity_id'], $activities, true)) {
                continue;
            }
            $description = trim((string) $entry['description']);
            if (str_contains($description, $note)) {
                return;
            }
            $text = $description === '' ? $note : $description . "\n" . $note;
            $this->addSql('UPDATE kimai2_timesheet SET description = ? WHERE id = ?', [$text, $entry['id']]);

            return;
        }

        $this->write(sprintf('Film day %s: no entry left for the note "%s", dropped.', $day['day_date'], $note));
    }
}
