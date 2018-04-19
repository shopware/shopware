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

namespace Shopware\Cart\Voucher;

use Shopware\Framework\Struct\Collection;

class VoucherCollection extends Collection
{
    /**
     * @var Voucher[]
     */
    protected $elements = [];

    public function add(Voucher $voucher): void
    {
        $this->elements[$this->getKey($voucher)] = $voucher;
    }

    public function remove(string $code): void
    {
        parent::doRemoveByKey($code);
    }

    public function removeElement(Voucher $voucher): void
    {
        parent::doRemoveByKey($this->getKey($voucher));
    }

    public function exists(Voucher $voucher): bool
    {
        return parent::has($this->getKey($voucher));
    }

    public function get(string $code): ? Voucher
    {
        if ($this->has($code)) {
            return $this->elements[$code];
        }

        return null;
    }

    protected function getKey(Voucher $element): string
    {
        return $element->getCode();
    }
}
