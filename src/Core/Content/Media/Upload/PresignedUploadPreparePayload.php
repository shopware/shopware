<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class PresignedUploadPreparePayload
{
    public function __construct(
        public readonly ?string $fileName = null,
        public readonly ?string $extension = null,
        public readonly ?string $mimeType = null,
        public readonly ?string $mediaFolderId = null,
        public readonly bool $private = false,
        public readonly ?string $mediaId = null,
    ) {
    }
}
