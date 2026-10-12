<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
interface PresignedUrlGeneratorInterface
{
    public function generate(MediaLocationStruct $location, string $mimeType, bool $private): PresignedUrlResult;

    /**
     * The result's `path` is a staging key outside the media paths; the object becomes media only through {@see promote()}.
     */
    public function generateStagingUpload(string $extension, bool $private): PresignedUrlResult;

    public function promote(string $stagingKey, string $path, bool $private, string $contentType, string $etag, bool $mayOverwrite): bool;

    public function deleteAbandonedStagingUploads(): void;

    public function isEnabled(): bool;

    public function isSupported(): bool;

    public function getFileMetadata(string $path, bool $private): ?FileMetadataResult;

    public function deleteFromStorage(string $path, bool $private): void;
}
