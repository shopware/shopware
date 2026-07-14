<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Maps one feature type between an app's declaration and its stored `app_feature` rows: on install
 * and update the lifecycle calls fromApp() then toPayload() to write the rows; reads rebuild the
 * config via fromPayload(). One implementation per type, tagged `shopware.app_feature.definition`.
 *
 * @internal
 *
 * @template T of AppFeatureConfig
 */
#[Package('framework')]
interface AppFeatureDefinition
{
    /**
     * The unique type of this feature: eg `mcp_tool` or `cookie`.
     */
    public function getType(): string;

    /**
     * The config class this definition maps.
     *
     * @return class-string<T>
     */
    public function getConfigClass(): string;

    /**
     * The configs this type declares in the app. Read from the manifest, or from app files via
     * $appFilesystem. Should return a list of all the configs for the feature, eg a list of the cookies or mcp tools.
     *
     * @return list<T>
     */
    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array;

    /**
     * Serializes a config into the row's JSON payload; fromPayload() must be able to rebuild it.
     * $stored is the config currently in the row on update (null on first install and on
     * AppFeatureStorage::save()), so a definition can keep shop-side changes across app updates.
     *
     * @param T $declared
     * @param T|null $stored
     *
     * @return array<string, mixed>
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array;

    /**
     * Rebuilds the config from a stored payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return T
     */
    public function fromPayload(array $payload): AppFeatureConfig;
}
