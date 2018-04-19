<?php
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
     * @var int|null
     */
    protected $paymentId;

    /**
     * @var int|null
     */
    protected $dispatchId;

    /**
     * @var int|null
     */
    protected $countryId;

    /**
     * @var int|null
     */
    protected $stateId;

    /**
     * @param int|null $paymentId
     * @param int|null $dispatchId
     * @param int      $countryId
     * @param int|null $stateId
     */
    public function __construct(
        ?int $paymentId = null,
        ?int $dispatchId = null,
        ?int $countryId = null,
        ?int $stateId = null
    ) {
        $this->paymentId = $paymentId;
        $this->dispatchId = $dispatchId;
        $this->countryId = $countryId;
        $this->stateId = $stateId;
    }

    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    public function getDispatchId(): ?int
    {
        return $this->dispatchId;
    }

    public function getCountryId(): ?int
    {
        return $this->countryId;
    }

    public function getStateId(): ?int
    {
        return $this->stateId;
    }

    public static function createFromContext(ShopContext $context): CheckoutScope
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
}
