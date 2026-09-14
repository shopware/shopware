<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Feature;

use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * Maps one feature type between an app's declaration and its stored `app_feature` rows: on install
 * and update the lifecycle calls fromApp() then toPayload() to write the rows; reads rebuild the
 * config via fromPayload(). One implementation per type, tagged `shopware.app_feature.definition`.
 *
 * The lifecycle also calls validate() before the rows are written and persisted() afterwards, so a
 * definition can reject the app's declaration or provision related resources without a separate
 * lifecycle handler.
 *
 * @internal
 *
 * @template T of AppFeatureConfig
 */
#[Package('framework')]
abstract class AppFeatureDefinition
{
    /**
     * The unique type of this feature: eg `mcp_tool` or `cookie`.
     */
    abstract public function getType(): string;

    /**
     * The config class this definition maps.
     *
     * @return class-string<T>
     */
    abstract public function getConfigClass(): string;

    /**
     * The configs this type declares in the app. Read from the manifest, or from app files via
     * $appFilesystem. Should return a list of all the configs for the feature, eg a list of the cookies or mcp tools.
     *
     * @return list<T>
     */
    abstract public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array;

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
    abstract public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array;

    /**
     * Rebuilds the config from a stored payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return T
     */
    abstract public function fromPayload(array $payload): AppFeatureConfig;

    /**
     * Called on install and update before the rows are written, with the configs declared by the app.
     * Throw to reject the app's declaration and abort the install/update.
     *
     * @param list<T> $configs
     */
    public function validate(array $configs, AppPersistContext $context): void
    {
    }

    /**
     * Called on install and update after the rows are written, with the configs declared by the app.
     * Use it to provision resources related to the declared configs.
     *
     * @param list<T> $configs
     */
    public function persisted(array $configs, AppPersistContext $context): void
    {
    }
}
