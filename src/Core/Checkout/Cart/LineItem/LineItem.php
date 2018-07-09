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

use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;

class LineItem extends Struct
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string|null
     */
    protected $label;

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
     * @var QuantityPriceDefinition|null
     */
    protected $priceDefinition;

    /**
     * @var Price|null
     */
    protected $price;

    /**
     * @var bool
     */
    protected $good = true;

    /**
     * @var int
     */
    protected $priority = 0;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var null|MediaStruct
     */
    protected $cover;

    /**
     * @var DeliveryInformation|null
     */
    protected $deliveryInformation;

    /**
     * @var LineItemCollection|null
     */
    protected $children;

    public function __construct(string $identifier, string $type, int $quantity = 1)
    {
        $this->identifier = $identifier;
        $this->quantity = $quantity;
        $this->type = $type;
    }

    public static function createFrom(Struct $object)
    {
        /** @var LineItem $object */
        $self = new static($object->identifier, $object->type, $object->quantity);

        foreach ($object as $propety => $value) {
            $self->$propety = $value;
        }

        return $self;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @throws InvalidQuantityException
     */
    public function setQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidQuantityException((string) $quantity);
        }
        $this->quantity = $quantity;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getPriceDefinition(): ?PriceDefinition
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinition $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function setPrice(?Price $price): void
    {
        $this->price = $price;
    }

    public function isGood(): bool
    {
        return $this->good;
    }

    public function setGood(bool $good): void
    {
        $this->good = $good;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCover(): ?MediaStruct
    {
        return $this->cover;
    }

    public function setCover(?MediaStruct $cover): void
    {
        $this->cover = $cover;
    }

    public function getDeliveryInformation(): ?DeliveryInformation
    {
        return $this->deliveryInformation;
    }

    public function setDeliveryInformation(?DeliveryInformation $deliveryInformation): void
    {
        $this->deliveryInformation = $deliveryInformation;
    }

    public function getChildren(): ?LineItemCollection
    {
        return $this->children;
    }

    public function setChildren(?LineItemCollection $children): void
    {
        $this->children = $children;
    }
}
