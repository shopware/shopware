<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Detail\Images;

use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Framework\Struct\Struct;

class DetailImagesPagelet extends Struct
{
    /**
     * @var ProductMediaCollection
     */
    private $productMedia;

    public function getProductMedia(): ?ProductMediaCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
    }
}
