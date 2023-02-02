<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Cms;

use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Symfony\Component\Asset\Packages;

class DefaultMediaResolver extends AbstractDefaultMediaResolver
{
    private const CMS_SNIPPET_DEFAULT_MEDIA_NAME = 'component.cms.defaultMedia';

    private Translator $translator;

    private Packages $packages;

    private AbstractDefaultMediaResolver $decorated;

    /**
     * @internal
     */
    public function __construct(AbstractDefaultMediaResolver $decorated, Translator $translator, Packages $packages)
    {
        $this->decorated = $decorated;
        $this->translator = $translator;
        $this->packages = $packages;
    }

    public function getDecorated(): AbstractDefaultMediaResolver
    {
        return $this->decorated;
    }

    public function getDefaultCmsMediaEntity(string $mediaAssetFilePath, string $snippetPath = self::CMS_SNIPPET_DEFAULT_MEDIA_NAME): ?MediaEntity
    {
        $media = $this->decorated->getDefaultCmsMediaEntity($mediaAssetFilePath);

        if (!$media) {
            return null;
        }

        $package = $this->packages->getPackage('asset');
        $snippetName = $snippetPath . '.' . $media->getFileName();

        // add translations to the media entity with a given snippet path
        $media->setTranslated([
            'title' => $this->translator->trans($snippetName . '.title'),
            'alt' => $this->translator->trans($snippetName . '.alt'),
        ]);

        // add the asset url
        $media->setUrl($package->getUrl('/bundles/' . $mediaAssetFilePath));

        return $media;
    }
}
