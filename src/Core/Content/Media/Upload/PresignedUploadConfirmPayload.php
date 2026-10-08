<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PresignedUploadConfirmPayload
{
    public function __construct(
        #[Assert\NotBlank]
        public string $uploadToken = '',
        #[Assert\Positive]
        public ?int $width = null,
        #[Assert\Positive]
        public ?int $height = null,
    ) {
    }
}
