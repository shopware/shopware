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
use Shopware\Media\Struct\MediaBasicCollection;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductStream\Struct\ProductStreamBasicCollection;

class CategoryDetailCollection extends CategoryBasicCollection
{
    /**
     * @var CategoryDetailStruct[]
     */
    protected $elements = [];

    public function getProductStreams(): ProductStreamBasicCollection
    {
        return new ProductStreamBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getProductStream();
            })
        );
    }

    public function getMedias(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (CategoryDetailStruct $category) {
                return $category->getMedia();
            })
        );
    }

    public function getProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getBlockedCustomerGroupsUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getBlockedCustomerGroupsUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getBlockedCustomerGroupss(): CustomerGroupBasicCollection
    {
        $collection = new CustomerGroupBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getBlockedCustomerGroupss()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
