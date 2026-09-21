<?php

/*
 * Dev helper: store a PNG or JPEG as the signature of a user.
 * Run: docker compose -f dev/compose.yaml exec -T kimai php /opt/kimai/var/plugins/DrehzettelBundle/dev/set_signature.php admin /tmp/sig.png
 */

use App\Entity\User;
use App\Kernel;
use KimaiPlugin\DrehzettelBundle\Repository\SignatureRepository;
use KimaiPlugin\DrehzettelBundle\Service\SignatureService;

require '/opt/kimai/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/opt/kimai/.env');
$kernel = new Kernel('prod', false);
$kernel->boot();
$registry = $kernel->getContainer()->get('doctrine');

$user = $registry->getManager()->getRepository(User::class)->findOneBy(['username' => $argv[1] ?? '']);
if ($user === null || !is_file($argv[2] ?? '')) {
    fwrite(STDERR, "Usage: set_signature.php USERNAME FILE\n");
    exit(1);
}

(new SignatureService(new SignatureRepository($registry)))->save($user, (string) file_get_contents($argv[2]));
echo "Signature stored for {$user->getUserIdentifier()}.\n";
