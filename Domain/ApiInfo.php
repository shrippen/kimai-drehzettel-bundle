<?php

namespace KimaiPlugin\DrehzettelBundle\Domain;

/**
 * Discovery answer of GET /api/drehzettel/ping. Clients check "features"
 * before using an addition to v1, so a v1 client works against an older
 * plugin by leaving the missing feature out.
 */
final class ApiInfo
{
    // Bump alongside composer.json's "version" when the plugin changes in a way external
    // clients might care about; bump API_VERSIONS when a new /v{n} prefix is introduced.
    public const PLUGIN_VERSION = '0.1.0';
    public const API_VERSIONS = ['v1'];

    // Additions to v1 since its first release, in the order they were added.
    public const FEATURES = ['errorCodes', 'engagements', 'defaults', 'extraPay'];

    /**
     * @param array{view: bool, manage: bool} $permissions of the token owner
     * @return array<string, mixed>
     */
    public static function ping(array $permissions): array
    {
        return [
            'installed' => true,
            'pluginVersion' => self::PLUGIN_VERSION,
            'apiVersions' => self::API_VERSIONS,
            'permissions' => $permissions,
            'features' => self::FEATURES,
        ];
    }
}
