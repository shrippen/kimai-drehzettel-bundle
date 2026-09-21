<?php

namespace KimaiPlugin\DrehzettelBundle\Service;

use App\Entity\User;
use KimaiPlugin\DrehzettelBundle\Entity\Signature;
use KimaiPlugin\DrehzettelBundle\Repository\SignatureRepository;

class SignatureService
{
    private const MAX_BYTES = 512 * 1024;
    private const MIME_TYPES = ['image/png', 'image/jpeg'];

    public function __construct(private readonly SignatureRepository $signatures)
    {
    }

    /**
     * @throws \InvalidArgumentException when the file is no PNG or JPEG image, or too large
     */
    public function save(User $user, string $bytes): void
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Signature image is larger than 512 KB.');
        }

        // Check the content, not the file name or the browser's claim.
        $info = @getimagesizefromstring($bytes);
        $mime = $info === false ? '' : (string) $info['mime'];
        if (!in_array($mime, self::MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Signature must be a PNG or JPEG image.');
        }

        $signature = $this->signatures->findForUser($user) ?? new Signature();
        $signature->setUser($user);
        $signature->setMime($mime);
        $signature->setData(base64_encode($bytes));
        $this->signatures->save($signature);
    }

    public function delete(User $user): void
    {
        $signature = $this->signatures->findForUser($user);
        if ($signature !== null) {
            $this->signatures->remove($signature);
        }
    }

    public function has(User $user): bool
    {
        return $this->signatures->findForUser($user) !== null;
    }

    // "data:image/png;base64,..." for embedding in HTML, null without signature.
    public function dataUri(User $user): ?string
    {
        $signature = $this->signatures->findForUser($user);
        if ($signature === null) {
            return null;
        }

        return 'data:' . $signature->getMime() . ';base64,' . $signature->getData();
    }
}
