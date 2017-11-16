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

namespace Shopware\Cart\LineItem;

use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Framework\Struct\Struct;

class LineItem extends Struct implements LineItemInterface
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var null|\Shopware\Cart\Price\Struct\PriceDefinition
     */
    protected $priceDefinition;

    public function __construct(
        string $identifier,
        string $type,
        int $quantity,
        array $payload = [],
        ?PriceDefinition $priceDefinition = null
    ) {
        $this->identifier = $identifier;
        $this->quantity = $quantity;
        $this->type = $type;
        $this->payload = $payload;
        $this->priceDefinition = $priceDefinition;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPriceDefinition(): ?PriceDefinition
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinition $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }
}
