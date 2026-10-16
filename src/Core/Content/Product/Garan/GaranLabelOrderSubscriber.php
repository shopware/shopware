<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the GARAN label values in the payload of the order's product line items, so the order keeps showing the
 * label it was placed with. Every order conversion passes here, whichever cart processors calculated the cart.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'storeLabels',
        ];
    }

    public function storeLabels(CartConvertedEvent $event): void
    {
        $order = $event->getConvertedCart();

        // an edited order keeps the GARAN label it was placed with
        $keepStoredLabels = $event->getCart()->getBehavior()?->hasPermission(ProductCartProcessor::SKIP_PRODUCT_RECALCULATION) ?? false;

        $productIds = [];

        foreach ($order['lineItems'] ?? [] as $key => $lineItem) {
            if (!\is_string($lineItem['productId'] ?? null)) {
                continue;
            }

            if ($keepStoredLabels && \array_key_exists('garanLabel', $lineItem['payload'] ?? [])) {
                continue;
            }

            $productIds[$key] = $lineItem['productId'];
        }

        // empty criteria would load every product
        if ($productIds === []) {
            return;
        }

        $criteria = new Criteria(array_values(array_unique($productIds)));
        $criteria->addAssociation('manufacturer');

        // variants inherit the guarantee and manufacturer
        $products = $event->getContext()->enableInheritance(
            fn (Context $context): ProductCollection => $this->productRepository->search($criteria, $context)->getEntities()
        );

        foreach ($productIds as $key => $productId) {
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $order['lineItems'][$key]['payload']['garanLabel'] = $this->resolver->resolveSnapshot($product);
        }

        $event->setConvertedCart($order);
    }
}
