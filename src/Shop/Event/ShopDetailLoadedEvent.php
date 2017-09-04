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

namespace Shopware\Shop\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;

class ShopDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shop.detail.loaded';

    /**
     * @var ShopDetailCollection
     */
    protected $shops;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShopDetailCollection $shops, TranslationContext $context)
    {
        $this->shops = $shops;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShops(): ShopDetailCollection
    {
        return $this->shops;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection(
            [
                new ShopBasicLoadedEvent($this->shops, $this->context),
                new CategoryBasicLoadedEvent($this->shops->getCategories(), $this->context),
                new LocaleBasicLoadedEvent($this->shops->getFallbackLocales(), $this->context),
                new ShippingMethodBasicLoadedEvent($this->shops->getShippingMethods(), $this->context),
                new ShopTemplateBasicLoadedEvent($this->shops->getShopTemplates(), $this->context),
                new AreaCountryBasicLoadedEvent($this->shops->getAreaCountries(), $this->context),
                new PaymentMethodBasicLoadedEvent($this->shops->getPaymentMethods(), $this->context),
                new CustomerGroupBasicLoadedEvent($this->shops->getCustomerGroups(), $this->context),
                new CurrencyBasicLoadedEvent($this->shops->getCurrencies(), $this->context),
            ]
        );
    }
}
