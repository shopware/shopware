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

namespace Shopware\PaymentMethod\Struct;

use Shopware\Framework\Struct\Collection;

class PaymentMethodBasicCollection extends Collection
{
    /**
     * @var PaymentMethodBasicStruct[]
     */
    protected $elements = [];

    public function add(PaymentMethodBasicStruct $paymentMethod): void
    {
        $key = $this->getKey($paymentMethod);
        $this->elements[$key] = $paymentMethod;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(PaymentMethodBasicStruct $paymentMethod): void
    {
        parent::doRemoveByKey($this->getKey($paymentMethod));
    }

    public function exists(PaymentMethodBasicStruct $paymentMethod): bool
    {
        return parent::has($this->getKey($paymentMethod));
    }

    public function getList(array $uuids): PaymentMethodBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? PaymentMethodBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (PaymentMethodBasicStruct $paymentMethod) {
                return $paymentMethod->getUuid();
            }
        );
    }

    public function getPluginUuids(): array
    {
        return $this->fmap(
            function (PaymentMethodBasicStruct $paymentMethod) {
                return $paymentMethod->getPluginUuid();
            }
        );
    }

    public function filterByPluginUuid(string $uuid): PaymentMethodBasicCollection
    {
        return $this->filter(
            function (PaymentMethodBasicStruct $paymentMethod) use ($uuid) {
                return $paymentMethod->getPluginUuid() === $uuid;
            }
        );
    }

    protected function getKey(PaymentMethodBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
