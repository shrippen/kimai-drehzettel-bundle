<?php

/*
 * This file is part of the DrehzettelBundle plugin for Kimai.
 */

namespace KimaiPlugin\DrehzettelBundle\DependencyInjection;

use KimaiPlugin\DrehzettelBundle\Service\Holiday\HolidayBundleLookup;
use KimaiPlugin\DrehzettelBundle\Service\HolidayLookupInterface;
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

        $this->configureHolidayLookup($container);
    }

    // KimaiPlugin\HolidayBundle (github.com/shrippen/kimai-holiday-bundle) is optional.
    // class_exists() only checks the autoloader, never fatals when the plugin is absent,
    // so this is the safe way to bind an optional cross-plugin service: services.yaml
    // never references HolidayBundle's classes directly, only this method does, and only
    // once they are confirmed present.
    private function configureHolidayLookup(ContainerBuilder $container): void
    {
        if (!class_exists(\KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository::class)
            || !class_exists(\KimaiPlugin\HolidayBundle\Service\UserWorkContract::class)) {
            return;
        }

        $container->register(HolidayBundleLookup::class, HolidayBundleLookup::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);
        $container->setAlias(HolidayLookupInterface::class, HolidayBundleLookup::class);
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

        // Registers the icon for EventSubscriber\ContractSubscriber's DayAddon marker
        // (type: 'drehzettel') with Tabler's icon() Twig filter/function
        // (see config/packages/tabler.yaml "icons:" in Kimai core). Without this, an
        // unregistered icon key is returned as-is and used as a CSS class, rendering
        // nothing - same gap the sibling HolidayBundle currently has for every absence
        // type except "vacation", which happens to reuse core's own "holiday" key.
        $container->prependExtensionConfig('tabler', [
            'icons' => [
                'drehzettel' => 'fas fa-clapperboard',
            ],
        ]);
    }
}
