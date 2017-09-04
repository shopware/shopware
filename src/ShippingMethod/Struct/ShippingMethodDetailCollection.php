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

namespace Shopware\ShippingMethod\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;

class ShippingMethodDetailCollection extends ShippingMethodBasicCollection
{
    /**
     * @var ShippingMethodDetailStruct[]
     */
    protected $elements = [];

    public function getShippingMethodPriceUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShippingMethodPriceUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShippingMethodPrices(): ShippingMethodPriceBasicCollection
    {
        $collection = new ShippingMethodPriceBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShippingMethodPrices()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getAreaCountryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAreaCountryUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAreaCountries(): AreaCountryBasicCollection
    {
        $collection = new AreaCountryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAreaCountries()->getIterator()->getArrayCopy());
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

    public function getHolidayUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getHolidayUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getHolidaies(): HolidayBasicCollection
    {
        $collection = new HolidayBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getHolidaies()->getIterator()->getArrayCopy());
        }

        return $collection;
    }

    public function getPaymentMethodUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPaymentMethodUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        $collection = new PaymentMethodBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPaymentMethods()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
