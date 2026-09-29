<?php

namespace KimaiPlugin\DrehzettelBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\DrehzettelBundle\Entity\Engagement;
use KimaiPlugin\DrehzettelBundle\Entity\MailRecipient;
use KimaiPlugin\DrehzettelBundle\Enum\MailRhythm;

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

    // The stored settings, or new ones for the engagement (not saved yet).
    public function settingsFor(Engagement $engagement): MailRecipient
    {
        $recipient = $this->findForEngagement($engagement);
        if ($recipient !== null) {
            return $recipient;
        }
        $recipient = new MailRecipient();
        $recipient->setEngagement($engagement);

        return $recipient;
    }

    public function save(MailRecipient $recipient): void
    {
        $this->getEntityManager()->persist($recipient);
        $this->getEntityManager()->flush();
    }

    /**
     * Settings with an automatic mail.
     *
     * @return list<MailRecipient>
     */
    public function findScheduled(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.rhythm != :off')
            ->andWhere("r.email != ''")
            ->setParameter('off', MailRhythm::OFF->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marks a send time as handled unless another run already did (cron and web request
     * at once): only the run that changes the row sends. Plain SQL, the entity is left
     * as loaded, so a later flush cannot write it back. Returns the previous value
     * for release(), or false when the slot was taken.
     */
    public function claim(MailRecipient $recipient, \DateTimeImmutable $slot): string|null|false
    {
        $previous = $this->getEntityManager()->getConnection()->fetchOne('SELECT last_slot FROM kimai2_ext_drehzettel_mail_recipient WHERE id = ?', [$recipient->getId()]);
        $key = $slot->format(\DATE_ATOM);
        if ($previous === $key) {
            return false;
        }

        $changed = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE kimai2_ext_drehzettel_mail_recipient SET last_slot = ? WHERE id = ? AND (last_slot IS NULL OR last_slot = ?)',
            [$key, $recipient->getId(), $previous === false ? null : $previous],
        );
        if ($changed !== 1) {
            return false;
        }

        return $previous === false ? null : $previous;
    }

    // Undoes claim() after a failed send, so the next run tries again.
    public function release(MailRecipient $recipient, ?string $previous): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE kimai2_ext_drehzettel_mail_recipient SET last_slot = ? WHERE id = ?',
            [$previous, $recipient->getId()],
        );
    }
}
