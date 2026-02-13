<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class FileMetadataResult
{
    public function __construct(
        public int $size,
        public \DateTimeImmutable $lastModified,
    ) {
    }
}
