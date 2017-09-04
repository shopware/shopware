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

namespace Shopware\CustomerAddress\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\AreaCountryState\Event\AreaCountryStateBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class CustomerAddressBasicLoadedEvent extends NestedEvent
{
    const NAME = 'customerAddress.basic.loaded';

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $customerAddresss;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CustomerAddressBasicCollection $customerAddresss, TranslationContext $context)
    {
        $this->customerAddresss = $customerAddresss;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCustomerAddresss(): CustomerAddressBasicCollection
    {
        return $this->customerAddresss;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection(
            [
                new AreaCountryBasicLoadedEvent($this->customerAddresss->getAreaCountries(), $this->context),
                new AreaCountryStateBasicLoadedEvent($this->customerAddresss->getAreaCountryStates(), $this->context),
            ]
        );
    }
}
