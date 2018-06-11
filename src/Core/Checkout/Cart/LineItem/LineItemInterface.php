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

namespace Shopware\Core\Checkout\Cart\LineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;

interface LineItemInterface extends \JsonSerializable
{
    /**
     * Defines the unique identifier for a cart line item
     * This identifier is used to find already existing items
     * and increase or decrease the quantity of them
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Defines the quantity of the line item.
     *
     * @return int
     */
    public function getQuantity(): int;

    /**
     * Returns a custom type for the line item which can be used instead
     * of `instance of`
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Allows to store payload with extra data for an line item which is not defined in the LineItemInterface
     *
     * @return array
     */
    public function getPayload(): array;

    /**
     * Sets the quantity of the line item which used to calculate to total amount
     *
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void;

    /**
     * Allows to define a pre calculated price which should be used instead of live requested prices.
     * Used for example if an order has to be recalculated if the shop owner changes order data
     *
     * @return null|\Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition
     */
    public function getPriceDefinition(): ? PriceDefinition;

    /**
     * Allows to define a pre calculated price which should be used instead of live requested prices.
     * Used for example if an order has to be recalculated if the shop owner changes order data
     *
     * @param \Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition $priceDefinition
     */
    public function setPriceDefinition(PriceDefinition $priceDefinition): void;
}
