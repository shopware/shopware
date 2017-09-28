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

namespace Shopware\CartBridge\View;

use Shopware\Cart\Delivery\Delivery;
use Shopware\Framework\Struct\Struct;

class ViewDelivery extends Struct
{
    /**
     * @var Delivery
     */
    protected $delivery;

    /**
     * @var ViewDeliveryPositionCollection
     */
    protected $viewPositions;

    public function __construct(Delivery $delivery, ViewDeliveryPositionCollection $viewPositions)
    {
        $this->delivery = $delivery;
        $this->viewPositions = $viewPositions;
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    public function getViewPositions(): ViewDeliveryPositionCollection
    {
        return $this->viewPositions;
    }
}
