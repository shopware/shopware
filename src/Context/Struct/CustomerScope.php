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
    protected $customerUuid;

    /**
     * @var string|null
     */
    protected $billingAddressUuid;

    /**
     * @var string|null
     */
    protected $shippingAddressUuid;

    /**
     * @var null|string
     */
    protected $customerGroupUuid;

    public function __construct(?string $customerId, ?string $customerGroupKey = null, ?string $billingId = null, ?string $shippingId = null)
    {
        $this->customerUuid = $customerId;
        $this->billingAddressUuid = $billingId;
        $this->shippingAddressUuid = $shippingId;
        $this->customerGroupUuid = $customerGroupKey;
    }

    public function getCustomerUuid(): ?string
    {
        return $this->customerUuid;
    }

    public function getBillingAddressUuid(): ?string
    {
        return $this->billingAddressUuid;
    }

    public function getShippingAddressUuid(): ?string
    {
        return $this->shippingAddressUuid;
    }

    public function getCustomerGroupUuid(): ?string
    {
        return $this->customerGroupUuid;
    }

    public static function createFromContext(ShopContext $context): self
    {
        if (!$context->getCustomer()) {
            return new self(null, $context->getCurrentCustomerGroup()->getUuid(), null, null);
        }

        return new self(
            $context->getCustomer()->getUuid(),
            $context->getCurrentCustomerGroup()->getUuid(),
            $context->getCustomer()->getActiveBillingAddress()->getUuid(),
            $context->getCustomer()->getActiveShippingAddress()->getUuid()
        );
    }
}
