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
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;

class LineItem extends Struct
{
    public const GOODS_PRIORITY = 100;

    public const VOUCHER_PRIORITY = 50;

    public const DISCOUNT_PRIORITY = 25;

    /**
     * @var string
     */
    protected $key;

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
    protected $priority = self::GOODS_PRIORITY;

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

    /**
     * @var Rule|null
     */
    protected $requirement;

    public function __construct(string $key, string $type, int $quantity = 1, int $priority = self::GOODS_PRIORITY)
    {
        $this->key = $key;
        $this->quantity = $quantity;
        $this->type = $type;
    }

    public static function createFrom(Struct $object)
    {
        /** @var LineItem $object */
        $self = new static($object->key, $object->type, $object->quantity, $object->priority);

        foreach ($object as $propety => $value) {
            $self->$propety = $value;
        }

        return $self;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @throws InvalidQuantityException
     */
    public function setQuantity(int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidQuantityException((string) $quantity);
        }
        $this->quantity = $quantity;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getPriceDefinition(): ?PriceDefinition
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinition $priceDefinition): self
    {
        $this->priceDefinition = $priceDefinition;
        return $this;
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function setPrice(?Price $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function isGood(): bool
    {
        return $this->good;
    }

    public function setGood(bool $good): self
    {
        $this->good = $good;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCover(): ?MediaStruct
    {
        return $this->cover;
    }

    public function setCover(?MediaStruct $cover): self
    {
        $this->cover = $cover;
        return $this;
    }

    public function getDeliveryInformation(): ?DeliveryInformation
    {
        return $this->deliveryInformation;
    }

    public function setDeliveryInformation(?DeliveryInformation $deliveryInformation): self
    {
        $this->deliveryInformation = $deliveryInformation;
        return $this;
    }

    public function getChildren(): ?LineItemCollection
    {
        return $this->children;
    }

    public function setChildren(?LineItemCollection $children): self
    {
        $this->children = $children;
        return $this;
    }

    public function setRequirement(?Rule $requirement): LineItem
    {
        $this->requirement = $requirement;

        return $this;
    }

    public function getRequirement(): ?Rule
    {
        return $this->requirement;
    }
}
