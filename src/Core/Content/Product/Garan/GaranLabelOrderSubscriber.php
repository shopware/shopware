<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Gives order line items without a stored GARAN label the label values of their product: stored when a cart is
 * converted, for line items that did not get them from the cart ({@see GaranLabelCartCollector}), and, until 6.8, in
 * memory when a sales channel reads orders placed before the label was stored, so the storefront does not look up each
 * product.
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
            OrderEvents::ORDER_LINE_ITEM_LOADED_EVENT => 'fillMissingLabels',
        ];
    }

    public function storeLabels(CartConvertedEvent $event): void
    {
        $order = $event->getConvertedCart();

        foreach ($this->resolveMissingLabels($order['lineItems'] ?? [], $event->getContext()) as $key => $label) {
            $order['lineItems'][$key]['payload'][GaranLabelResolver::PAYLOAD_KEY] = $label;
        }

        $event->setConvertedCart($order);
    }

    /**
     * @param EntityLoadedEvent<OrderLineItemEntity> $event
     */
    public function fillMissingLabels(EntityLoadedEvent $event): void
    {
        // @deprecated tag:v6.8.0 - remove this method and its event, order line items without a stored label then show no label
        if (Feature::isActive('v6.8.0.0')) {
            return;
        }

        // only sales channels render the label, the Admin API and the system keep reading what is stored
        if (!$event->getContext()->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $lineItems = $event->getEntities();

        $labels = $this->resolveMissingLabels(
            array_map(static fn (OrderLineItemEntity $lineItem): array => [
                'productId' => $lineItem->getProductId(),
                'payload' => $lineItem->getPayload(),
            ], $lineItems),
            $event->getContext(),
        );

        foreach ($labels as $key => $label) {
            $lineItems[$key]->setPayloadValue(GaranLabelResolver::PAYLOAD_KEY, $label);
        }
    }

    /**
     * A new order keeps the label its cart showed, an edited order the label it was placed with.
     *
     * @param array<array-key, array{productId?: string|null, payload?: array<string, mixed>|null}> $lineItems
     *
     * @return array<array-key, array{guaranteeMonths: int, brand: string, modelIdentifier: string}|null> label per key of the line items without one
     */
    private function resolveMissingLabels(array $lineItems, Context $context): array
    {
        $productIds = [];

        foreach ($lineItems as $key => $lineItem) {
            if (!\is_string($lineItem['productId'] ?? null)) {
                continue;
            }

            if (\array_key_exists(GaranLabelResolver::PAYLOAD_KEY, $lineItem['payload'] ?? [])) {
                continue;
            }

            $productIds[$key] = $lineItem['productId'];
        }

        // empty criteria would load every product
        if ($productIds === []) {
            return [];
        }

        $criteria = new Criteria(array_values(array_unique($productIds)));
        $criteria->addAssociation('manufacturer');

        // variants inherit the guarantee and manufacturer
        $products = $context->enableInheritance(
            fn (Context $context): ProductCollection => $this->productRepository->search($criteria, $context)->getEntities()
        );

        return array_map(function (string $productId) use ($products): ?array {
            $product = $products->get($productId);

            return $product !== null ? $this->resolver->resolveSnapshot($product) : null;
        }, $productIds);
    }
}
