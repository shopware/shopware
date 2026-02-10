<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class FileMetadataResult extends Struct
{
    public function __construct(
        public readonly int $size,
        public readonly \DateTimeImmutable $lastModified,
    ) {
    }
}
