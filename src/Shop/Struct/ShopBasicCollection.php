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

namespace Shopware\Shop\Struct;

use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Framework\Struct\Collection;
use Shopware\Locale\Struct\LocaleBasicCollection;

class ShopBasicCollection extends Collection
{
    /**
     * @var ShopBasicStruct[]
     */
    protected $elements = [];

    public function add(ShopBasicStruct $shop): void
    {
        $key = $this->getKey($shop);
        $this->elements[$key] = $shop;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ShopBasicStruct $shop): void
    {
        parent::doRemoveByKey($this->getKey($shop));
    }

    public function exists(ShopBasicStruct $shop): bool
    {
        return parent::has($this->getKey($shop));
    }

    public function getList(array $uuids): ShopBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ShopBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getUuid();
            }
        );
    }

    public function getMainUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getMainUuid();
            }
        );
    }

    public function filterByMainUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getMainUuid() === $uuid;
            }
        );
    }

    public function getTemplateUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getTemplateUuid();
            }
        );
    }

    public function filterByTemplateUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getTemplateUuid() === $uuid;
            }
        );
    }

    public function getDocumentTemplateUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getDocumentTemplateUuid();
            }
        );
    }

    public function filterByDocumentTemplateUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getDocumentTemplateUuid() === $uuid;
            }
        );
    }

    public function getCategoryUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getCategoryUuid();
            }
        );
    }

    public function filterByCategoryUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getCategoryUuid() === $uuid;
            }
        );
    }

    public function getLocaleUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getLocaleUuid();
            }
        );
    }

    public function filterByLocaleUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getLocaleUuid() === $uuid;
            }
        );
    }

    public function getCurrencyUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getCurrencyUuid();
            }
        );
    }

    public function filterByCurrencyUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getCurrencyUuid() === $uuid;
            }
        );
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getCustomerGroupUuid();
            }
        );
    }

    public function filterByCustomerGroupUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getCustomerGroupUuid() === $uuid;
            }
        );
    }

    public function getFallbackLocaleUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getFallbackLocaleUuid();
            }
        );
    }

    public function filterByFallbackLocaleUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getFallbackLocaleUuid() === $uuid;
            }
        );
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getPaymentMethodUuid();
            }
        );
    }

    public function filterByPaymentMethodUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getPaymentMethodUuid() === $uuid;
            }
        );
    }

    public function getShippingMethodUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getShippingMethodUuid();
            }
        );
    }

    public function filterByShippingMethodUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getShippingMethodUuid() === $uuid;
            }
        );
    }

    public function getAreaCountryUuids(): array
    {
        return $this->fmap(
            function (ShopBasicStruct $shop) {
                return $shop->getAreaCountryUuid();
            }
        );
    }

    public function filterByAreaCountryUuid(string $uuid): ShopBasicCollection
    {
        return $this->filter(
            function (ShopBasicStruct $shop) use ($uuid) {
                return $shop->getAreaCountryUuid() === $uuid;
            }
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(
                function (ShopBasicStruct $shop) {
                    return $shop->getCurrency();
                }
            )
        );
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(
                function (ShopBasicStruct $shop) {
                    return $shop->getLocale();
                }
            )
        );
    }

    protected function getKey(ShopBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
