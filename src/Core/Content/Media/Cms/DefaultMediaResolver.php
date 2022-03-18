<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    private const BUNDLE_NAME = 'core';

    private string $projectDir;

    private string $bundleName;

    public function __construct(string $projectDir, string $bundleName = self::BUNDLE_NAME)
    {
        $this->projectDir = $projectDir;
        $this->bundleName = $bundleName;
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function getDefaultCmsMediaEntity(string $cmsAssetFileName): ?MediaEntity
    {
        $filePath = sprintf(
            '%s/bundles/%s/%s%s',
            $this->projectDir,
            $this->bundleName,
            self::CMS_DEFAULT_ASSETS_PATH,
            $cmsAssetFileName
        );

        if (!file_exists($filePath)) {
            return null;
        }

        $mimeType = mime_content_type($filePath);
        $pathInfo = pathinfo($filePath);

        if (!$mimeType || !\array_key_exists('extension', $pathInfo)) {
            return null;
        }

        $media = new MediaEntity();
        $media->setFileName($cmsAssetFileName);
        $media->setMimeType($mimeType);
        $media->setFileExtension($pathInfo['extension']);

        return $media;
    }
}
