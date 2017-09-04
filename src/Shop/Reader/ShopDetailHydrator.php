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

namespace Shopware\Shop\Reader;

use Shopware\AreaCountry\Reader\AreaCountryBasicHydrator;
use Shopware\Category\Reader\CategoryBasicHydrator;
use Shopware\CustomerGroup\Reader\CustomerGroupBasicHydrator;
use Shopware\Framework\Struct\Hydrator;
use Shopware\PaymentMethod\Reader\PaymentMethodBasicHydrator;
use Shopware\ShippingMethod\Reader\ShippingMethodBasicHydrator;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\ShopTemplate\Reader\ShopTemplateBasicHydrator;

class ShopDetailHydrator extends Hydrator
{
    /**
     * @var ShopBasicHydrator
     */
    private $basicHydrator;

    /**
     * @var CategoryBasicHydrator
     */
    private $categoryBasicHydrator;
    /**
     * @var ShippingMethodBasicHydrator
     */
    private $shippingMethodBasicHydrator;
    /**
     * @var ShopTemplateBasicHydrator
     */
    private $shopTemplateBasicHydrator;
    /**
     * @var AreaCountryBasicHydrator
     */
    private $areaCountryBasicHydrator;
    /**
     * @var PaymentMethodBasicHydrator
     */
    private $paymentMethodBasicHydrator;
    /**
     * @var CustomerGroupBasicHydrator
     */
    private $customerGroupBasicHydrator;

    public function __construct(
        ShopBasicHydrator $basicHydrator,
        CategoryBasicHydrator $categoryBasicHydrator,
        ShippingMethodBasicHydrator $shippingMethodBasicHydrator,
        ShopTemplateBasicHydrator $shopTemplateBasicHydrator,
        AreaCountryBasicHydrator $areaCountryBasicHydrator,
        PaymentMethodBasicHydrator $paymentMethodBasicHydrator,
        CustomerGroupBasicHydrator $customerGroupBasicHydrator
    ) {
        $this->basicHydrator = $basicHydrator;
        $this->categoryBasicHydrator = $categoryBasicHydrator;
        $this->shippingMethodBasicHydrator = $shippingMethodBasicHydrator;
        $this->shopTemplateBasicHydrator = $shopTemplateBasicHydrator;
        $this->areaCountryBasicHydrator = $areaCountryBasicHydrator;
        $this->paymentMethodBasicHydrator = $paymentMethodBasicHydrator;
        $this->customerGroupBasicHydrator = $customerGroupBasicHydrator;
    }

    public function hydrate(array $data): ShopDetailStruct
    {
        $shop = ShopDetailStruct::createFrom($this->basicHydrator->hydrate($data));
        $shop->setCategory($this->categoryBasicHydrator->hydrate($data));
        $shop->setShippingMethod($this->shippingMethodBasicHydrator->hydrate($data));
        $shop->setShopTemplate($this->shopTemplateBasicHydrator->hydrate($data));
        $shop->setAreaCountry($this->areaCountryBasicHydrator->hydrate($data));
        $shop->setPaymentMethod($this->paymentMethodBasicHydrator->hydrate($data));
        $shop->setCustomerGroup($this->customerGroupBasicHydrator->hydrate($data));
        $shop->setCurrencyUuids(array_filter(explode('|', (string)$data['__shop_currency_uuids'])));

        return $shop;
    }
}
