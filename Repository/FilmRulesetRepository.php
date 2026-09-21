<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\FilmRuleset;

/**
 * @extends ServiceEntityRepository<FilmRuleset>
 */
class FilmRulesetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmRuleset::class);
    }

    public function findByName(string $name): ?FilmRuleset
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return list<FilmRuleset>
     */
    public function findAllSorted(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function save(FilmRuleset $ruleset): void
    {
        $this->getEntityManager()->persist($ruleset);
        $this->getEntityManager()->flush();
    }
}
