<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\PriceModifier\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationCollection;
use Shopware\Core\Checkout\PriceModifier\PriceCollectorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads the currently persisted order_price_modification rows for the order this cart originated
 * from (if any) into the shared CartDataCollection bag, for OrderPriceModificationProcessor to
 * reapply as-is.
 */
#[Package('checkout')]
final class OrderPriceModificationCollector implements PriceCollectorInterface
{
    final public const DATA_KEY = 'order-price-modifications';

    /**
     * @internal
     *
     * @param EntityRepository<OrderPriceModificationCollection> $orderPriceModificationRepository
     */
    public function __construct(private readonly EntityRepository $orderPriceModificationRepository)
    {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $orderId = $original->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId();
        $orderVersionId = $original->getExtensionOfType(OrderConverter::ORIGINAL_VERSION_ID, IdStruct::class)?->getId();

        if ($orderId === null || $orderVersionId === null) {
            // No order exists yet -- nothing to load.
            $data->set(self::DATA_KEY, new OrderPriceModificationCollection());

            return;
        }

        // Scoped to $orderVersionId, not the live version in $context, so an in-progress admin
        // draft-order edit is visible here too.
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('position'));

        /** @var OrderPriceModificationCollection $modifications */
        $modifications = $this->orderPriceModificationRepository
            ->search($criteria, $context->getContext()->createWithVersionId($orderVersionId))
            ->getEntities();

        $data->set(self::DATA_KEY, $modifications);
    }
}
