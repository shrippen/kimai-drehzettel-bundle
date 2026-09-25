<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;

/**
 * @extends ServiceEntityRepository<Engagement>
 */
class EngagementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Engagement::class);
    }

    /**
     * @return list<Engagement>
     */
    public function findForUserProject(User $user, Project $project): array
    {
        return $this->findBy(['user' => $user, 'project' => $project], ['validFrom' => 'ASC']);
    }

    public function findActive(User $user, Project $project, \DateTimeImmutable $date): ?Engagement
    {
        return $this->createQueryBuilder('e')
            ->where('e.user = :user')
            ->andWhere('e.project = :project')
            ->andWhere('e.validFrom <= :date')
            ->andWhere('e.validTo IS NULL OR e.validTo >= :date')
            ->setParameter('user', $user)
            ->setParameter('project', $project)
            ->setParameter('date', $date, 'date_immutable')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Engagement> active on $date, by project name
     */
    public function findActiveForUser(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.project', 'p')
            ->where('e.user = :user')
            ->andWhere('e.validFrom <= :date')
            ->andWhere('e.validTo IS NULL OR e.validTo >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date, 'date_immutable')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Engagement>
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['validFrom' => 'DESC']);
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Engagement> everyone's engagements, newest first
     */
    public function findAllSorted(): array
    {
        return $this->findBy([], ['validFrom' => 'DESC']);
    }

    public function save(Engagement $engagement): void
    {
        $this->getEntityManager()->persist($engagement);
        $this->getEntityManager()->flush();
    }

    public function remove(Engagement $engagement): void
    {
        $this->getEntityManager()->remove($engagement);
        $this->getEntityManager()->flush();
    }
}
