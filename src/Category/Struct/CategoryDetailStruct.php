<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductStream\Struct\ProductStreamBasicStruct;

class CategoryDetailStruct extends CategoryBasicStruct
{
    /**
     * @var ProductStreamBasicStruct|null
     */
    protected $productStream;

    /**
     * @var MediaBasicStruct|null
     */
    protected $media;

    /**
     * @var string[]
     */
    protected $productUuids = [];

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var string[]
     */
    protected $blockedCustomerGroupsUuids = [];

    /**
     * @var CustomerGroupBasicCollection
     */
    protected $blockedCustomerGroups;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();
        $this->blockedCustomerGroups = new CustomerGroupBasicCollection();
    }

    public function getProductStream(): ?ProductStreamBasicStruct
    {
        return $this->productStream;
    }

    public function setProductStream(?ProductStreamBasicStruct $productStream): void
    {
        $this->productStream = $productStream;
    }

    public function getMedia(): ?MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(?MediaBasicStruct $media): void
    {
        $this->media = $media;
    }

    public function getProductUuids(): array
    {
        return $this->productUuids;
    }

    public function setProductUuids(array $productUuids): void
    {
        $this->productUuids = $productUuids;
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function setProducts(ProductBasicCollection $products): void
    {
        $this->products = $products;
    }

    public function getBlockedCustomerGroupsUuids(): array
    {
        return $this->blockedCustomerGroupsUuids;
    }

    public function setBlockedCustomerGroupsUuids(array $blockedCustomerGroupsUuids): void
    {
        $this->blockedCustomerGroupsUuids = $blockedCustomerGroupsUuids;
    }

    public function getBlockedCustomerGroups(): CustomerGroupBasicCollection
    {
        return $this->blockedCustomerGroups;
    }

    public function setBlockedCustomerGroups(CustomerGroupBasicCollection $blockedCustomerGroups): void
    {
        $this->blockedCustomerGroups = $blockedCustomerGroups;
    }
}
