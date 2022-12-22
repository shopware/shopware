<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductMediaEntity>
 *
 * @package inventory
 */
class ProductMediaCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductMediaEntity $productMedia) {
            return $productMedia->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductMediaEntity $productMedia) use ($id) {
            return $productMedia->getProductId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductMediaEntity $productMedia) {
            return $productMedia->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductMediaEntity $productMedia) use ($id) {
            return $productMedia->getMediaId() === $id;
        });
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(function (ProductMediaEntity $productMedia) {
                return $productMedia->getMedia();
            })
        );
    }

    public function getApiAlias(): string
    {
        return 'product_media_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaEntity::class;
    }
}
