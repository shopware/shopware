<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadFinalizePayload
{
    public function __construct(
        #[Assert\NotBlank]
        public string $fileName = '',
        #[Assert\NotBlank]
        public string $extension = '',
        #[Assert\NotBlank]
        public string $mimeType = '',
        #[Assert\NotBlank]
        public string $path = '',
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }
}
