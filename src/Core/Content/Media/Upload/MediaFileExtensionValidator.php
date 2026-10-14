<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\MimeTypes;

/**
 * @internal
 *
 * Validates an upload's file extension against the configured allow list, which subscribers to
 * MediaFileExtensionWhitelistEvent may modify at runtime, and checks that the content type stored with the file
 * fits that extension. Shared by the legacy FileSaver flow and the presigned-upload flow so both paths run the
 * same validation semantics.
 */
#[Package('discovery')]
readonly class MediaFileExtensionValidator
{
    /**
     * Content types a browser renders as a document that can run scripts when the file is opened directly.
     * Every other XML based type (`+xml` suffix, e.g. `image/svg+xml`) is treated the same way.
     */
    private const ACTIVE_CONTENT_TYPES = [
        'text/html',
        'application/xhtml+xml',
        'text/xml',
        'application/xml',
    ];

    /**
     * Content types accepted for an extension in addition to its registered ones. File type detection can report a
     * valid SVG as plain XML. This is safe, because the SVG content validator checks every `.svg` file by its
     * extension, whatever content type was detected.
     */
    private const ADDITIONAL_CONTENT_TYPES = [
        'svg' => ['text/xml', 'application/xml'],
    ];

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

    /**
     * S3 and other object storages serve a file with the content type stored at upload time, not with the type
     * of its extension. An HTML or SVG document uploaded as `.jpg` would therefore still run its scripts when
     * opened. Such content is only accepted with an extension that belongs to its own content type, so it passes
     * the extension allow list and the content validators of that type.
     */
    public function validateContentType(string $extension, string $mimeType): void
    {
        $contentType = mb_strtolower(FileInfoHelper::stripParameters($mimeType));

        if (!$this->isActiveContentType($contentType)) {
            return;
        }

        $fileExtension = mb_strtolower($extension);
        $allowedContentTypes = [
            ...MimeTypes::getDefault()->getMimeTypes($fileExtension),
            ...self::ADDITIONAL_CONTENT_TYPES[$fileExtension] ?? [],
        ];

        if (\in_array($contentType, $allowedContentTypes, true)) {
            return;
        }

        throw MediaException::invalidFile(\sprintf('The content type "%s" does not match the file extension "%s"', $contentType, $extension));
    }

    private function isActiveContentType(string $contentType): bool
    {
        return \in_array($contentType, self::ACTIVE_CONTENT_TYPES, true) || str_ends_with($contentType, '+xml');
    }
}
