<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(ProductMediaEntity $entity)
 * @method void                    set(string $key, ProductMediaEntity $entity)
 * @method ProductMediaEntity[]    getIterator()
 * @method ProductMediaEntity[]    getElements()
 * @method ProductMediaEntity|null get(string $key)
 * @method ProductMediaEntity|null first()
 * @method ProductMediaEntity|null last()
 */
class ProductMediaCollection extends EntityCollection
{
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

    protected function getExpectedClass(): string
    {
        return ProductMediaEntity::class;
    }
}
