<?php

/*
 * This file is part of the DrehzettelBundle plugin for Kimai.
 */

namespace KimaiPlugin\DrehzettelBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class DrehzettelExtension extends Extension implements PrependExtensionInterface
{
    // drehzettel: own engagements, weeks, PDFs and signature. drehzettel_manage: all engagements and rulesets.
    private const PERMISSION_OWN = 'drehzettel';
    private const PERMISSION_MANAGE = 'drehzettel_manage';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'paths' => [
                \dirname(__DIR__) . '/Resources/views' => null,
            ],
        ]);

        $own = [self::PERMISSION_OWN];
        $all = [self::PERMISSION_OWN, self::PERMISSION_MANAGE];

        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => $all,
                    'ROLE_ADMIN' => $all,
                    'ROLE_TEAMLEAD' => $own,
                    'ROLE_USER' => $own,
                ],
            ],
        ]);
    }
}
