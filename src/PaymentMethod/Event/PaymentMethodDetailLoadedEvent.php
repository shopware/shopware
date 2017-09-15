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

namespace Shopware\PaymentMethod\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailCollection;
use Shopware\Shop\Event\ShopBasicLoadedEvent;

class PaymentMethodDetailLoadedEvent extends NestedEvent
{
    const NAME = 'paymentMethod.detail.loaded';

    /**
     * @var PaymentMethodDetailCollection
     */
    protected $paymentMethods;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(PaymentMethodDetailCollection $paymentMethods, TranslationContext $context)
    {
        $this->paymentMethods = $paymentMethods;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPaymentMethods(): PaymentMethodDetailCollection
    {
        return $this->paymentMethods;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new PaymentMethodBasicLoadedEvent($this->paymentMethods, $this->context),
            new ShopBasicLoadedEvent($this->paymentMethods->getShops(), $this->context),
            new AreaCountryBasicLoadedEvent($this->paymentMethods->getCountries(), $this->context),
        ]);
    }
}
