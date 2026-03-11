<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('discovery')]
class PresignedUploadFinalizePayload
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $fileName = '',
        #[Assert\NotBlank]
        public readonly string $extension = '',
        #[Assert\NotBlank]
        public readonly string $mimeType = '',
        #[Assert\NotBlank]
        public readonly string $path = '',
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {
    }
}
