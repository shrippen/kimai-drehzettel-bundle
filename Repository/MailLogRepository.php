<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailLog;

/**
 * @extends ServiceEntityRepository<MailLog>
 */
class MailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailLog::class);
    }

    public function findForSlot(Engagement $engagement, \DateTimeImmutable $slot): ?MailLog
    {
        return $this->findOneBy(['engagement' => $engagement, 'slot' => $slot->format(\DATE_ATOM)]);
    }

    /**
     * @return list<MailLog> newest attempt first: a retry moves its row up
     */
    public function findLatest(Engagement $engagement, int $limit): array
    {
        return $this->findBy(['engagement' => $engagement], ['attemptedAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function save(MailLog $entry): void
    {
        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();
    }
}
