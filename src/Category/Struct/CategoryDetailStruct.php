<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
    protected $blockedCustomerGroupss;

    public function __construct()
    {
        $this->products = new ProductBasicCollection();
        $this->blockedCustomerGroupss = new CustomerGroupBasicCollection();
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

    public function getBlockedCustomerGroupss(): CustomerGroupBasicCollection
    {
        return $this->blockedCustomerGroupss;
    }

    public function setBlockedCustomerGroupss(CustomerGroupBasicCollection $blockedCustomerGroupss): void
    {
        $this->blockedCustomerGroupss = $blockedCustomerGroupss;
    }
}
