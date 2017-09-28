<?php declare(strict_types=1);

namespace Shopware\Media\Extension;

use Shopware\Media\UrlGeneratorInterface;
use Shopware\ProductMedia\Event\ProductMediaBasicLoadedEvent;
use Shopware\ProductMedia\Extension\ProductMediaExtension as CoreProductMediaExtension;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ProductMediaUrlExtension extends CoreProductMediaExtension
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function productMediaBasicLoaded(ProductMediaBasicLoadedEvent $event): void
    {
        /** @var ProductMediaBasicStruct $productMedia */
        foreach ($event->getProductMedias() as $productMedia) {
            $media = $productMedia->getMedia();

            if (!$media) {
                continue;
            }

            $media->setUrl($this->urlGenerator->getUrl($media->getFileName()));
        }
    }
}
