<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Signature;

/**
 * @extends ServiceEntityRepository<Signature>
 */
class SignatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signature::class);
    }

    public function findForUser(User $user): ?Signature
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function save(Signature $signature): void
    {
        $this->getEntityManager()->persist($signature);
        $this->getEntityManager()->flush();
    }

    public function remove(Signature $signature): void
    {
        $this->getEntityManager()->remove($signature);
        $this->getEntityManager()->flush();
    }
}
