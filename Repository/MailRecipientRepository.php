<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailRecipient;

/**
 * @extends ServiceEntityRepository<MailRecipient>
 */
class MailRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailRecipient::class);
    }

    public function findForEngagement(Engagement $engagement): ?MailRecipient
    {
        return $this->findOneBy(['engagement' => $engagement]);
    }

    public function remember(Engagement $engagement, string $email): void
    {
        $recipient = $this->findForEngagement($engagement) ?? new MailRecipient();
        $recipient->setEngagement($engagement);
        $recipient->setEmail($email);
        $this->getEntityManager()->persist($recipient);
        $this->getEntityManager()->flush();
    }
}
