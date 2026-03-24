<?php

declare(strict_types=1);

namespace Shopware\Administration\Framework\Asset;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\AssetUploadEvent;

/**
 * @internal
 *
 * The `AssetUploadEvent` event is used to put the vite entrypoint and manifest files at the end of the copy process.
 * This ensures, that the old admin assets still can be served until all new assets are copied.
 * Otherwise, the vite entrypoint and manifest files were copied first, but the new assets were still missing and in progress to be copied.
 * This would cause the admin to crash until all new assets were copied.
 */
#[Package('framework')]
class AssetUploadListener
{
    private const VITE_ENTRYPOINTS_JSON = 'administration/.vite/entrypoints.json';
    private const VITE_MANIFEST_JSON = 'administration/.vite/manifest.json';

    public function __invoke(AssetUploadEvent $event): void
    {
        $filesToUpload = $event->filesToUpload;
        $changedFiles = false;

        $viteEntryPointsJsonKey = array_find_key($event->filesToUpload, static fn (string $file): bool => $file === self::VITE_ENTRYPOINTS_JSON);
        if ($viteEntryPointsJsonKey !== null) {
            unset($filesToUpload[$viteEntryPointsJsonKey]);
            $filesToUpload[] = self::VITE_ENTRYPOINTS_JSON;
            $changedFiles = true;
        }

        $viteManifestJsonKey = array_find_key($event->filesToUpload, static fn (string $file): bool => $file === self::VITE_MANIFEST_JSON);
        if ($viteManifestJsonKey !== null) {
            unset($filesToUpload[$viteManifestJsonKey]);
            $filesToUpload[] = self::VITE_MANIFEST_JSON;
            $changedFiles = true;
        }

        if ($changedFiles) {
            $event->filesToUpload = array_values($filesToUpload);
        }
    }
}
