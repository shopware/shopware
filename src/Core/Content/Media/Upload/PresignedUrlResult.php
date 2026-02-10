<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class PresignedUrlResult extends Struct
{
    public function __construct(
        public readonly string $url,
        public readonly string $path,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
