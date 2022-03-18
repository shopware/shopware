<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Cms;

use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Bridge\Twig\Extension\AssetExtension;

class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    private const BUNDLE_NAME = 'storefront';

    private const CMS_SNIPPET_DEFAULT_MEDIA_NAME = 'component.cms.defaultMedia';

    private string $projectDir;

    private Translator $translator;

    private AssetExtension $assetExtension;

    public function __construct(string $projectDir, Translator $translator, AssetExtension $assetExtension)
    {
        $this->projectDir = $projectDir;
        $this->translator = $translator;
        $this->assetExtension = $assetExtension;
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
            self::BUNDLE_NAME,
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

        $fileNameWithoutExtension = explode('.', $cmsAssetFileName)[0];
        $snippetName = self::CMS_SNIPPET_DEFAULT_MEDIA_NAME . '.' . $fileNameWithoutExtension;

        $media = new MediaEntity();
        $media->setFileName($cmsAssetFileName);
        $media->setMimeType($mimeType);
        $media->setFileExtension($pathInfo['extension']);

        $media->setTranslated([
            'title' => $this->translator->trans($snippetName . '.title'),
            'alt' => $this->translator->trans($snippetName . '.alt'),
        ]);

        $media->setUrl($this->assetExtension->getAssetUrl(sprintf(
            '/bundles/%s/%s%s',
            self::BUNDLE_NAME,
            self::CMS_DEFAULT_ASSETS_PATH,
            $cmsAssetFileName
        )));

        return $media;
    }
}
