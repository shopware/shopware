<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUrlResult
{
    public function __construct(
        public string $url,
        public string $path,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
