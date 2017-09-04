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

namespace Shopware\Product\Struct;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\ProductDetail\Struct\ProductDetailBasicCollection;

class ProductDetailCollection extends ProductBasicCollection
{
    /**
     * @var ProductDetailStruct[]
     */
    protected $elements = [];

    public function getDetailUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDetailUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDetails(): ProductDetailBasicCollection
    {
        $collection = new ProductDetailBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDetails()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getCategoryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategoryUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        $collection = new CategoryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategories()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
