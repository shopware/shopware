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

namespace Shopware\Context\Struct;

class CheckoutScope implements \JsonSerializable
{
    /**
     * @var string|null
     */
    protected $paymentMethodUuid;

    /**
     * @var string|null
     */
    protected $shippingMethodUuid;

    /**
     * @var string|null
     */
    protected $countryUuid;

    /**
     * @var string|null
     */
    protected $stateUuid;

    public function __construct(
        ?string $paymentMethodUuid = null,
        ?string $shippingMethodUuid = null,
        ?string $countryUuid = null,
        ?string $stateUuid = null
    ) {
        $this->paymentMethodUuid = $paymentMethodUuid;
        $this->shippingMethodUuid = $shippingMethodUuid;
        $this->countryUuid = $countryUuid;
        $this->stateUuid = $stateUuid;
    }

    public function getPaymentMethodUuid(): ?string
    {
        return $this->paymentMethodUuid;
    }

    public function getShippingMethodUuid(): ?string
    {
        return $this->shippingMethodUuid;
    }

    public function getCountryUuid(): ?string
    {
        return $this->countryUuid;
    }

    public function getStateUuid(): ?string
    {
        return $this->stateUuid;
    }

    public static function createFromContext(ShopContext $context): self
    {
        $location = $context->getShippingLocation();

        return new self(
            $context->getPaymentMethod()->getUuid(),
            $context->getShippingMethod()->getUuid(),
            $location->getCountry()->getUuid(),
            $location->getState() ? $location->getState()->getUuid() : null
        );
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
