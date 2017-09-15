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

namespace Shopware\ProductVote\Struct;

use Shopware\Framework\Struct\Collection;

class ProductVoteBasicCollection extends Collection
{
    /**
     * @var ProductVoteBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductVoteBasicStruct $productVote): void
    {
        $key = $this->getKey($productVote);
        $this->elements[$key] = $productVote;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductVoteBasicStruct $productVote): void
    {
        parent::doRemoveByKey($this->getKey($productVote));
    }

    public function exists(ProductVoteBasicStruct $productVote): bool
    {
        return parent::has($this->getKey($productVote));
    }

    public function getList(array $uuids): ProductVoteBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductVoteBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getUuid();
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductVoteBasicCollection
    {
        return $this->filter(function (ProductVoteBasicStruct $productVote) use ($uuid) {
            return $productVote->getProductUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ProductVoteBasicCollection
    {
        return $this->filter(function (ProductVoteBasicStruct $productVote) use ($uuid) {
            return $productVote->getShopUuid() === $uuid;
        });
    }

    protected function getKey(ProductVoteBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
