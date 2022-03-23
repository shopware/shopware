<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity
    {
        $filePath = $this->projectDir . '/bundles/' . $mediaAssetFilePath;

        if (!file_exists($filePath)) {
            return null;
        }

        $mimeType = mime_content_type($filePath);
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
