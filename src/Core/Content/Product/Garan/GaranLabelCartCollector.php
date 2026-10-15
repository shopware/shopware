<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Stores the GARAN label values in the payload of the cart's product line items, so the storefront renders the
 * label without loading each product again, and the order takes it over from the cart.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelCartCollector implements CartDataCollectorInterface
{
    public function __construct(private readonly GaranLabelResolver $resolver)
    {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // an edited order keeps the GARAN label it was placed with, see GaranLabelOrderSubscriber
        if ($behavior->hasPermission(CheckoutPermissions::SKIP_PRODUCT_RECALCULATION)) {
            return;
        }

        // the products the ProductCartProcessor loaded in this calculation, the other line items keep their stored label
        $products = new ProductCollection($data->filterInstance(ProductEntity::class));

        foreach ($original->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $product = $products->get((string) $lineItem->getReferencedId());

            if ($product !== null) {
                $lineItem->setPayloadValue(GaranLabelResolver::PAYLOAD_KEY, $this->resolver->resolveSnapshot($product));
            }
        }
    }
}
