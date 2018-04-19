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

class CustomerScope implements \JsonSerializable
{
    /**
     * @var int|null
     */
    protected $customerId;

    /**
     * @var int|null
     */
    protected $billingId;

    /**
     * @var int|null
     */
    protected $shippingId;

    /**
     * @var null|string
     */
    private $customerGroupKey;

    public function __construct(?int $customerId, ?string $customerGroupKey = null, ?int $billingId = null, ?int $shippingId = null)
    {
        $this->customerId = $customerId;
        $this->billingId = $billingId;
        $this->shippingId = $shippingId;
        $this->customerGroupKey = $customerGroupKey;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getBillingId(): ?int
    {
        return $this->billingId;
    }

    public function getShippingId(): ?int
    {
        return $this->shippingId;
    }

    public function getCustomerGroupKey(): ?string
    {
        return $this->customerGroupKey;
    }

    public static function createFromContext(ShopContext $context): CustomerScope
    {
        if (!$context->getCustomer()) {
            return new self(null, $context->getCurrentCustomerGroup()->getKey(), null, null);
        }

        return new self(
            $context->getCustomer()->getId(),
            $context->getCurrentCustomerGroup()->getKey(),
            $context->getCustomer()->getActiveBillingAddress()->getId(),
            $context->getCustomer()->getActiveShippingAddress()->getId()
        );
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
