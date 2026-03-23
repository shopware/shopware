<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class AssetUploadEvent
{
    /**
     * @internal
     *
     * @param array<string, string> $localManifest
     * @param array<string, string> $remoteManifest
     * @param list<string> $filesToUpload
     * @param list<string> $filesToDelete
     */
    public function __construct(
        public readonly string $originDir,
        public readonly string $targetDirectory,
        public readonly array $localManifest,
        public readonly array $remoteManifest,
        public array $filesToUpload,
        public array $filesToDelete,
    ) {
    }
}
