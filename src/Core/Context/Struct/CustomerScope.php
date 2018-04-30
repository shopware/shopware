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

use Shopware\Framework\Struct\Struct;

class CustomerScope extends Struct
{
    /**
     * @var string|null
     */
    protected $customerId;

    /**
     * @var string|null
     */
    protected $billingAddressId;

    /**
     * @var string|null
     */
    protected $shippingAddressId;

    /**
     * @var null|string
     */
    protected $customerGroupId;

    public function __construct(?string $customerId, ?string $customerGroupId = null, ?string $billingId = null, ?string $shippingId = null)
    {
        $this->customerId = $customerId;
        $this->billingAddressId = $billingId;
        $this->shippingAddressId = $shippingId;
        $this->customerGroupId = $customerGroupId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getBillingAddressId(): ?string
    {
        return $this->billingAddressId;
    }

    public function getShippingAddressId(): ?string
    {
        return $this->shippingAddressId;
    }

    public function getCustomerGroupId(): ?string
    {
        return $this->customerGroupId;
    }

    public static function createFromContext(StorefrontContext $context): self
    {
        if (!$context->getCustomer()) {
            return new self(null, $context->getCurrentCustomerGroup()->getId(), null, null);
        }

        return new self(
            $context->getCustomer()->getId(),
            $context->getCurrentCustomerGroup()->getId(),
            $context->getCustomer()->getActiveBillingAddress()->getId(),
            $context->getCustomer()->getActiveShippingAddress()->getId()
        );
    }
}
