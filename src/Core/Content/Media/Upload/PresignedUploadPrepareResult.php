<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadPrepareResult
{
    public function __construct(
        public string $mediaId,
        public string $url,
        public string $path,
        public string $expiresAt,
        public bool $isDuplicate,
    ) {
    }
}
