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

namespace Shopware\Cart\Delivery;

use Shopware\Framework\Struct\Collection;

class DeliveryInformationCollection extends Collection
{
    /**
     * @var DeliveryInformation[]
     */
    protected $elements = [];

    /**
     * @param DeliveryInformation[] $elements
     */
    public function __construct(array $elements = [])
    {
        foreach ($elements as $key => $element) {
            $this->add($key, $element);
        }
    }

    public function add(string $key, DeliveryInformation $deliveryInformation): void
    {
        $this->elements[$key] = $deliveryInformation;
    }

    public function remove(string $key): void
    {
        parent::doRemoveByKey($key);
    }

    public function get(string $key): ? DeliveryInformation
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }
}
