<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductMedia;

use Shopware\Core\Content\Media\MediaBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class ProductMediaBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductMediaBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductMediaBasicStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($id) {
            return $productMedia->getProductId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($id) {
            return $productMedia->getMediaId() === $id;
        });
    }

    public function getMedia(): \Shopware\Core\Content\Media\MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (ProductMediaBasicStruct $productMedia) {
                return $productMedia->getMedia();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaBasicStruct::class;
    }
}
