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

namespace Shopware\TaxAreaRule\Struct;

use Shopware\Framework\Struct\Collection;

class TaxAreaRuleBasicCollection extends Collection
{
    /**
     * @var TaxAreaRuleBasicStruct[]
     */
    protected $elements = [];

    public function add(TaxAreaRuleBasicStruct $taxAreaRule): void
    {
        $key = $this->getKey($taxAreaRule);
        $this->elements[$key] = $taxAreaRule;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(TaxAreaRuleBasicStruct $taxAreaRule): void
    {
        parent::doRemoveByKey($this->getKey($taxAreaRule));
    }

    public function exists(TaxAreaRuleBasicStruct $taxAreaRule): bool
    {
        return parent::has($this->getKey($taxAreaRule));
    }

    public function getList(array $uuids): TaxAreaRuleBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? TaxAreaRuleBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getUuid();
            }
        );
    }

    public function getAreaUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getAreaUuid();
            }
        );
    }

    public function filterByAreaUuid(string $uuid): TaxAreaRuleBasicCollection
    {
        return $this->filter(
            function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
                return $taxAreaRule->getAreaUuid() === $uuid;
            }
        );
    }

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getAreaCountryUuid();
            }
        );
    }

    public function filterByAreaCountryUuid(string $uuid): TaxAreaRuleBasicCollection
    {
        return $this->filter(
            function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
                return $taxAreaRule->getAreaCountryUuid() === $uuid;
            }
        );
    }

    public function getAreaCountryStateUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getAreaCountryStateUuid();
            }
        );
    }

    public function filterByAreaCountryStateUuid(string $uuid): TaxAreaRuleBasicCollection
    {
        return $this->filter(
            function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
                return $taxAreaRule->getAreaCountryStateUuid() === $uuid;
            }
        );
    }

    public function getTaxUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getTaxUuid();
            }
        );
    }

    public function filterByTaxUuid(string $uuid): TaxAreaRuleBasicCollection
    {
        return $this->filter(
            function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
                return $taxAreaRule->getTaxUuid() === $uuid;
            }
        );
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(
            function (TaxAreaRuleBasicStruct $taxAreaRule) {
                return $taxAreaRule->getCustomerGroupUuid();
            }
        );
    }

    public function filterByCustomerGroupUuid(string $uuid): TaxAreaRuleBasicCollection
    {
        return $this->filter(
            function (TaxAreaRuleBasicStruct $taxAreaRule) use ($uuid) {
                return $taxAreaRule->getCustomerGroupUuid() === $uuid;
            }
        );
    }

    protected function getKey(TaxAreaRuleBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
