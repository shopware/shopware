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

namespace Shopware\Product\Gateway;

use Shopware\Framework\Struct\Collection;
use Shopware\Product\Struct\ProductIdentity;
use Shopware\Search\SearchResultInterface;
use Shopware\Search\SearchResultTrait;

class ProductSearchResult extends Collection implements SearchResultInterface
{
    use SearchResultTrait;

    /**
     * @var ProductIdentity[]
     */
    protected $elements = [];

    public function __construct(array $elements, int $total)
    {
        parent::__construct($elements);
        $this->total = $total;
    }

    public function add(ProductIdentity $identity): void
    {
        $this->elements[$this->getKey($identity)] = $identity;
    }

    public function remove(string $number): void
    {
        parent::doRemoveByKey($number);
    }

    public function removeElement(ProductIdentity $identity): void
    {
        parent::doRemoveByKey($this->getKey($identity));
    }

    public function get(string $number): ? ProductIdentity
    {
        if ($this->has($number)) {
            return $this->elements[$number];
        }

        return null;
    }

    public function getProductIds(): array
    {
        return $this->getKeys();
    }

    public function getVariantIds(): array
    {
        return $this->map(function (ProductIdentity $identity) {
            return $identity->getVariantUuid();
        });
    }

    public function getNumbers(): array
    {
        return $this->map(function (ProductIdentity $identity) {
            return $identity->getNumber();
        });
    }

    protected function getKey(ProductIdentity $element): string
    {
        return $element->getNumber();
    }
}
