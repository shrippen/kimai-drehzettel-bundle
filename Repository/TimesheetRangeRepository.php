<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Read-only access to Kimai timesheets of one user and project.
 *
 * @extends ServiceEntityRepository<Timesheet>
 */
class TimesheetRangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Timesheet::class);
    }

    /**
     * Finished entries that begin in [from, to). Running timers are skipped.
     *
     * @return list<Timesheet>
     */
    public function findClosed(User $user, Project $project, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.project = :project')
            ->andWhere('t.begin >= :from')
            ->andWhere('t.begin < :to')
            ->andWhere('t.end IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.begin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * User, project and begin as last loaded from the database, before unsaved changes.
     * Begin is in the entry's stored timezone, like Timesheet::getBegin().
     *
     * @return array{User, Project, \DateTimeImmutable}|null null for a new entry
     */
    public function stored(Timesheet $timesheet): ?array
    {
        $data = $this->getEntityManager()->getUnitOfWork()->getOriginalEntityData($timesheet);
        $user = $data['user'] ?? null;
        $project = $data['project'] ?? null;
        $begin = $data['begin'] ?? null;
        $zone = $data['timezone'] ?? null;
        if (!$user instanceof User || !$project instanceof Project || !$begin instanceof \DateTimeInterface || !\is_string($zone)) {
            return null;
        }

        return [$user, $project, \DateTimeImmutable::createFromInterface($begin)->setTimezone(new \DateTimeZone($zone))];
    }
}
