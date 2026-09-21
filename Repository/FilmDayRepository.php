<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\FilmDay;

/**
 * @extends ServiceEntityRepository<FilmDay>
 */
class FilmDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmDay::class);
    }

    public function findOne(Engagement $engagement, \DateTimeImmutable $date): ?FilmDay
    {
        return $this->findOneBy(['engagement' => $engagement, 'date' => $date]);
    }

    /**
     * @return list<FilmDay> days in [from, to)
     */
    public function findRange(Engagement $engagement, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.engagement = :engagement')
            ->andWhere('d.date >= :from')
            ->andWhere('d.date < :to')
            ->setParameter('engagement', $engagement)
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(FilmDay $day): void
    {
        $this->getEntityManager()->persist($day);
        $this->getEntityManager()->flush();
    }
}
