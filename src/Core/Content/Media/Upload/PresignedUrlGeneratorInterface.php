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

    public function isEnabled(): bool;

    public function isSupported(): bool;

    public function getFileMetadata(string $path, bool $private): ?FileMetadataResult;

    public function deleteFromStorage(string $path, bool $private): void;
}
