<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderGenerator implements DemodataGeneratorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var OrderDefinition
     */
    private $orderDefinition;

    private array $contexts = [];

    private AmountCalculator $amountCalculator;

    private DeliveryProcessor $deliveryProcessor;

    private CartCalculator $cartCalculator;

    public function __construct(
        Connection $connection,
        AbstractSalesChannelContextFactory $contextFactory,
        CartService $cartService,
        OrderConverter $orderConverter,
        EntityWriterInterface $writer,
        OrderDefinition $orderDefinition,
        AmountCalculator $amountCalculator,
        DeliveryProcessor $deliveryProcessor,
        CartCalculator $cartCalculator
    ) {
        $this->connection = $connection;
        $this->contextFactory = $contextFactory;
        $this->cartService = $cartService;
        $this->orderConverter = $orderConverter;
        $this->writer = $writer;
        $this->orderDefinition = $orderDefinition;
        $this->amountCalculator = $amountCalculator;
        $this->deliveryProcessor = $deliveryProcessor;
        $this->cartCalculator = $cartCalculator;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $sql = <<<'SQL'
SELECT LOWER(HEX(product.id)) AS id, product.price, trans.name
FROM product
LEFT JOIN product_translation trans ON product.id = trans.product_id
LIMIT 150
SQL;

        $products = $this->connection->fetchAll($sql);
        $customerIds = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM customer LIMIT 10');
        $customerIds = array_column($customerIds, 'id');
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $lineItems = array_map(
            function ($product) {
                $productId = $product['id'];

                return (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, random_int(1, 10)))
                    ->setStackable(true)
                    ->setRemovable(true);
            },
            $products
        );

        $salesChannelContext = $this->contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $blueprint = $this->cartService->getCart(Uuid::randomHex(), $salesChannelContext);
        $blueprint->addLineItems(new LineItemCollection($lineItems));
        $blueprint->markModified();

        $blueprint = $this->cartCalculator->calculate($blueprint, $salesChannelContext);

        $orders = [];

        $lineItems = new LineItemCollection($lineItems);

        for ($i = 1; $i <= $numberOfItems; ++$i) {
            $customerId = $context->getFaker()->randomElement($customerIds);

            $salesChannelContext = $this->getContext($customerId);

            $itemCount = random_int(3, 5);

            $offset = random_int(0, $lineItems->count()) - 10;

            $new = $blueprint->getLineItems()->slice($offset, $itemCount);

            $cart = $this->cartService->createNew($salesChannelContext->getToken(), 'demo-data');
            $cart->setData($blueprint->getData());
            $cart->addLineItems($new);
            $cart->addExtension(OrderConverter::ORIGINAL_ORDER_NUMBER, new IdStruct(Uuid::randomHex()));

            $this->calculateAmount($salesChannelContext, $cart);
            $this->deliveryProcessor->process($cart->getData(), $cart, $cart, $salesChannelContext, new CartBehavior());

            $tempOrder = $this->orderConverter->convertToOrder($cart, $salesChannelContext, new OrderConversionContext());

            $tempOrder['orderDateTime'] = (new \DateTime())->modify('-' . random_int(0, 30) . ' days')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $orders[] = $tempOrder;

            if (\count($orders) >= 20) {
                $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
                $context->getConsole()->progressAdvance(\count($orders));
                $orders = [];
            }
        }

        if (!empty($orders)) {
            $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
        }

        $context->getConsole()->progressFinish();
    }

    private function getContext(string $customerId): SalesChannelContext
    {
        if (isset($this->contexts[$customerId])) {
            return $this->contexts[$customerId];
        }

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ];

        $context = $this->contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, $options);

        return $this->contexts[$customerId] = $context;
    }

    private function calculateAmount(SalesChannelContext $context, Cart $cart): void
    {
        $amount = $this->amountCalculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );

        $cart->setPrice($amount);
    }
}
