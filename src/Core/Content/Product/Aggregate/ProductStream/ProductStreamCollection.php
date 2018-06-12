<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream;


use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\System\Listing\ListingSortingCollection;

class ProductStreamCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductStream\ProductStreamStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductStreamStruct
    {
        return parent::get($id);
    }

    public function current(): ProductStreamStruct
    {
        return parent::current();
    }

    public function getListingSortingIds(): array
    {
        return $this->fmap(function (ProductStreamStruct $productStream) {
            return $productStream->getListingSortingId();
        });
    }

    public function filterByListingSortingId(string $id): self
    {
        return $this->filter(function (ProductStreamStruct $productStream) use ($id) {
            return $productStream->getListingSortingId() === $id;
        });
    }

    public function getListingSortings(): ListingSortingCollection
    {
        return new ListingSortingCollection(
            $this->fmap(function (ProductStreamStruct $productStream) {
                return $productStream->getListingSorting();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamStruct::class;
    }
}
