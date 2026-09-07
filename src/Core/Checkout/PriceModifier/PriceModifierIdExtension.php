<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Cart extension tracking which PriceModifier::$id values are active, so the generic storefront
 * delete route (CartLineItemController::deletePriceModifier()) can remove any of them without
 * knowing which plugin produced it. Persists automatically via Cart::$extensions round-tripping
 * through CartPersister.
 */
#[Package('checkout')]
final class PriceModifierIdExtension extends Struct
{
    final public const KEY = 'cart-price-modifier-ids';

    /**
     * @var array<string>
     */
    protected array $ids = [];

    public function add(string $id): void
    {
        if ($id === '') {
            return;
        }

        if (!\in_array($id, $this->ids, true)) {
            $this->ids[] = $id;
        }
    }

    public function remove(string $id): void
    {
        $this->ids = array_values(array_filter(
            $this->ids,
            static fn (string $existing): bool => $existing !== $id
        ));
    }

    public function has(string $id): bool
    {
        return \in_array($id, $this->ids, true);
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getApiAlias(): string
    {
        return 'cart_price_modifier_id_extension';
    }
}
