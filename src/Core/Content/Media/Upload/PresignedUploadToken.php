<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signed context for a presigned upload. Minted at request time and handed back at confirm; it carries everything
 * needed to create the media entity once the bytes exist, so no database row is written before confirm. Serialisation
 * and signing are handled by {@see PresignedUploadTokenSigner}.
 */
#[Package('discovery')]
readonly class PresignedUploadToken
{
    public function __construct(
        public string $mediaId,
        public string $path,
        public string $fileName,
        public string $extension,
        public string $mimeType,
        public bool $private,
        public ?string $mediaFolderId,
        public bool $deduplicate,
        public bool $isReplace,
        public \DateTimeImmutable $uploadedAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
