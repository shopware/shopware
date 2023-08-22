<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
}
