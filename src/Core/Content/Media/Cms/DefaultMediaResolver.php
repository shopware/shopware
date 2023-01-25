<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('content')]
class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity
    {
        $filePath = '/bundles/' . $mediaAssetFilePath;

        if (!$this->filesystem->fileExists($filePath)) {
            return null;
        }

        $mimeType = $this->filesystem->mimeType($filePath);
        $pathInfo = pathinfo($filePath);

        if (!$mimeType || !\array_key_exists('extension', $pathInfo)) {
            return null;
        }

        $media = new MediaEntity();
        $media->setFileName($pathInfo['filename']);
        $media->setMimeType($mimeType);
        $media->setFileExtension($pathInfo['extension']);

        return $media;
    }
}
