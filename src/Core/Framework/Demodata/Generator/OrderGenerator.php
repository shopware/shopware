<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
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

    public function __construct(
        Connection $connection,
        AbstractSalesChannelContextFactory $contextFactory,
        CartService $cartService,
        OrderConverter $orderConverter,
        EntityWriterInterface $writer,
        OrderDefinition $orderDefinition
    ) {
        $this->connection = $connection;
        $this->contextFactory = $contextFactory;
        $this->cartService = $cartService;
        $this->orderConverter = $orderConverter;
        $this->writer = $writer;
        $this->orderDefinition = $orderDefinition;
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $sql = <<<SQL
SELECT LOWER(HEX(product.id)) AS id, product.price, trans.name
FROM product
LEFT JOIN product_translation trans ON product.id = trans.product_id
LIMIT 500
SQL;

        $products = $this->connection->fetchAll($sql);
        $customerIds = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as id FROM customer LIMIT 200');
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

        $payload = [];

        $contexts = [];
        $lineItems = new LineItemCollection($lineItems);

        for ($i = 1; $i <= $numberOfItems; ++$i) {
            $token = Uuid::randomHex();

            $customerId = $context->getFaker()->randomElement($customerIds);

            $options = [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
            ];

            if (isset($contexts[$customerId])) {
                $salesChannelContext = $contexts[$customerId];
            } else {
                $salesChannelContext = $this->contextFactory->create($token, Defaults::SALES_CHANNEL, $options);
                $taxStates = [CartPrice::TAX_STATE_FREE, CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET];
                $salesChannelContext->setTaxState($taxStates[array_rand($taxStates)]);
                $contexts[$customerId] = $salesChannelContext;
            }

            $itemCount = random_int(3, 5);

            $offset = random_int(0, $lineItems->count()) - 10;

            $new = $lineItems->slice($offset, $itemCount);

            $cart = $this->cartService->createNew($token, 'demo-data');
            $cart->addLineItems($new);

            $cart = $this->cartService->recalculate($cart, $salesChannelContext);

            $tempOrder = $this->orderConverter->convertToOrder($cart, $salesChannelContext, new OrderConversionContext());
            $tempOrder['orderDateTime'] = (new \DateTime())->modify('-' . random_int(0, 30) . ' days')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $payload[] = $tempOrder;

            if (\count($payload) >= 20) {
                $this->writer->upsert($this->orderDefinition, $payload, $writeContext);
                $context->getConsole()->progressAdvance(\count($payload));
                $payload = [];
            }
        }

        if (!empty($payload)) {
            $this->writer->upsert($this->orderDefinition, $payload, $writeContext);
        }

        $context->getConsole()->progressFinish();
    }
}
