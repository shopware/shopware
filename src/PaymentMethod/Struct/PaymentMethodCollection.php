<?php
declare(strict_types=1);
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

class PaymentMethodCollection extends Collection
{
    /**
     * @var PaymentMethod[]
     */
    protected $elements = [];

    public function add(PaymentMethod $paymentMethod): void
    {
        $key = $this->getKey($paymentMethod);
        $this->elements[$key] = $paymentMethod;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(PaymentMethod $paymentMethod): void
    {
        parent::doRemoveByKey($this->getKey($paymentMethod));
    }

    public function exists(PaymentMethod $paymentMethod): bool
    {
        return parent::has($this->getKey($paymentMethod));
    }

    public function get(int $id): ? PaymentMethod
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    protected function getKey(PaymentMethod $element): int
    {
        return $element->getId();
    }
}
