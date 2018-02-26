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
    protected $paymentMethodId;

    /**
     * @var string|null
     */
    protected $shippingMethodId;

    /**
     * @var string|null
     */
    protected $countryId;

    /**
     * @var string|null
     */
    protected $stateId;

    /**
     * @var string|null
     */
    protected $cartToken;

    public function __construct(
        ?string $paymentMethodId = null,
        ?string $shippingMethodId = null,
        ?string $countryId = null,
        ?string $stateId = null,
        ?string $cartToken = null
    ) {
        $this->paymentMethodId = $paymentMethodId;
        $this->shippingMethodId = $shippingMethodId;
        $this->countryId = $countryId;
        $this->stateId = $stateId;
        $this->cartToken = $cartToken;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }

    public function getShippingMethodId(): ?string
    {
        return $this->shippingMethodId;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getStateId(): ?string
    {
        return $this->stateId;
    }

    public static function createFromContext(StorefrontContext $context): self
    {
        $location = $context->getShippingLocation();

        return new self(
            $context->getPaymentMethod()->getId(),
            $context->getShippingMethod()->getId(),
            $location->getCountry()->getId(),
            $location->getState() ? $location->getState()->getId() : null
        );
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    public function getCartToken(): ?string
    {
        return $this->cartToken;
    }
}
