<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractStockStorage
{
    abstract public function getDecorated(): self;

    /**
     * This method provides an extension point to augment the stock data when it is loaded.
     *
     * This method is called when loading products via:
     * * \Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader
     * * \Shopware\Core\Content\Product\Stock\LoadProductStockSubscriber
     *
     * This data will be set directly on the products, overwriting their existing values. Furthermore, the keys specified below and any extra data will be added
     * as an array extension to the product under the key `stock_data`.
     */
    abstract public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection;

    /**
     * This method should be used to update the stock value of a product for a given order item change.
     *
     * @param list<StockAlteration> $changes
     */
    abstract public function alter(array $changes, Context $context): void;

    /**
     * This method is executed when a product is created or updated. It can be used to perform some calculations such as update the `available` flag based on the new stock level.
     *
     * @param list<string> $productIds
     */
    abstract public function index(array $productIds, Context $context): void;

    /**
     * Called inside the transaction that writes an order, before the order is written.
     * Implementations lock the stock of the given products until that transaction ends and return the ids of
     * the products whose stock does not cover the ordered quantity. The order is rejected for these products.
     *
     * @param array<string, int> $quantities ordered quantity per product id
     *
     * @return list<string>
     */
    public function lockForOrder(array $quantities, Context $context): array
    {
        try {
            return $this->getDecorated()->lockForOrder($quantities, $context);
        } catch (DecorationPatternException) {
            // Replacements of the core storage manage their stock on their own
            return [];
        }
    }
}
