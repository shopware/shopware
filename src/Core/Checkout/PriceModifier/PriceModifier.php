<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * A single, human-readable cart-level price adjustment applied by a PriceProcessorInterface, e.g.
 * "Voucher: -50.00" or "Rush order fee: 15.00". Purely for display -- the monetary effect on
 * Cart::price is already applied via the PriceCollections a PriceProcessorInterface returns.
 *
 * $id/$type/$referencedId identify which order_price_modification row (once an order exists) this
 * corresponds to, and which plugin contributed it; all nullable for backward compatibility.
 *
 * $payload is free-form plugin data (e.g. a redeemed voucher code) persisted as-is to
 * order_price_modification.payload, for templates or the plugin itself to read back.
 */
#[Package('checkout')]
final class PriceModifier extends Struct
{
    public function __construct(
        protected string $label,
        protected PriceModifierCalculatedPrice $price,
        protected PriceModifierPriceDefinitionInterface $priceDefinition,
        protected ?string $id = null,
        protected ?string $type = null,
        protected ?string $referencedId = null,
        protected ?string $description = null,
        /**
         * @var array<string, mixed>|null
         */
        protected ?array $payload = null,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPrice(): PriceModifierCalculatedPrice
    {
        return $this->price;
    }

    /**
     * A copy carrying a different calculated price, e.g. once Processor had to cap it.
     */
    public function withPrice(PriceModifierCalculatedPrice $price): self
    {
        $modifier = clone $this;
        $modifier->price = $price;

        return $modifier;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getReferencedId(): ?string
    {
        return $this->referencedId;
    }

    public function getPriceDefinition(): PriceModifierPriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_modifier';
    }
}
