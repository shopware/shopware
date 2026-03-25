<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AssetUploadEvent
{
    /**
     * @internal
     *
     * @param list<string> $filesToUpload
     * @param list<string> $filesToDelete
     */
    public function __construct(
        public array $filesToUpload,
        public array $filesToDelete,
    ) {
    }
}
