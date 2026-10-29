<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionCaptureBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionCaptureRefundBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class OrderTransactionCaptureRefundPositionRepositoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCaptureRefundPositionCollection>
     */
    private EntityRepository $refundPositionRepository;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->refundPositionRepository = static::getContainer()->get('order_transaction_capture_refund_position.repository');
    }

    public function testReadPositionContainsOrderLineItemVersionId(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $refund = (new OrderTransactionCaptureRefundBuilder($ids, 'refund', $ids->get('capture')))
            ->add('positions', [[
                'id' => $ids->get('position'),
                'orderLineItemId' => $ids->get('line-item'),
                'quantity' => 1,
                'amount' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ]])
            ->build();

        $capture = (new OrderTransactionCaptureBuilder($ids, 'capture', $ids->get('transaction')))
            ->addRefund('refund', $refund)
            ->build();

        $transaction = (new OrderTransactionBuilder($ids, 'transaction'))
            ->addCapture('capture', $capture)
            ->build();

        $order = (new OrderBuilder($ids, '10000'))
            ->addLineItem([
                'id' => $ids->get('line-item'),
                'identifier' => 'line-item',
                'quantity' => 1,
                'label' => 'line-item',
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ])
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->create([$order], $context);

        $position = $this->refundPositionRepository
            ->search(new Criteria([$ids->get('position')]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($position);
        static::assertSame($ids->get('line-item'), $position->getOrderLineItemId());
        static::assertSame(Defaults::LIVE_VERSION, $position->getOrderLineItemVersionId());
    }
}
