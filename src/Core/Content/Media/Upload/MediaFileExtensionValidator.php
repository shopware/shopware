<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Validates an upload's file extension against the configured allow list, which subscribers to
 * MediaFileExtensionWhitelistEvent may modify at runtime. Shared by the legacy FileSaver flow and
 * the presigned-upload flow so both paths run the same validation semantics.
 */
#[Package('discovery')]
readonly class MediaFileExtensionValidator
{
    public function __construct(
        private MediaFileExtensionListProvider $mediaFileExtensionListProvider,
    ) {
    }

    public function validate(string $extension, bool $isPrivate, Context $context, string $mediaId = ''): void
    {
        $fileExtension = mb_strtolower($extension);

        foreach ($this->mediaFileExtensionListProvider->getAllowedExtensions($isPrivate, $context) as $allowed) {
            if ($fileExtension === mb_strtolower((string) $allowed)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported($mediaId, $fileExtension);
    }
}
