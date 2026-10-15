<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Subscriber\OrderTransactionVersionMergeSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeVersionMergeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderTransactionVersionMergeSubscriber::class)]
class OrderTransactionVersionMergeSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [BeforeVersionMergeEvent::class => 'onBeforeVersionMerge'],
            OrderTransactionVersionMergeSubscriber::getSubscribedEvents()
        );
    }

    public function testDropsTheAmountOfTransactionsWhosePaymentIsNoLongerDue(): void
    {
        $versionId = Uuid::randomHex();
        $paidId = Uuid::randomHex();
        $openId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $amount = ['totalPrice' => 261.88];

        $lookedUpIds = null;
        $lookedUpVersionId = null;
        $repository = StaticEntityRepository::of(OrderTransactionCollection::class, [
            static function (Criteria $criteria, Context $context) use (&$lookedUpIds, &$lookedUpVersionId, $paidId): array {
                $lookedUpIds = $criteria->getIds();
                $lookedUpVersionId = $context->getVersionId();

                return [$paidId];
            },
        ]);

        $event = new BeforeVersionMergeEvent([
            'insert' => [],
            'update' => [
                'order_transaction' => [
                    ['id' => $paidId, 'versionId' => $versionId, 'paymentMethodId' => $paymentMethodId, 'amount' => $amount],
                    ['id' => $openId, 'versionId' => $versionId, 'paymentMethodId' => $paymentMethodId, 'amount' => $amount],
                ],
            ],
            'delete' => [],
        ]);

        (new OrderTransactionVersionMergeSubscriber($repository))->onBeforeVersionMerge($event);

        static::assertSame([$paidId, $openId], $lookedUpIds);
        static::assertSame($versionId, $lookedUpVersionId);
        static::assertSame([
            ['id' => $paidId, 'versionId' => $versionId, 'paymentMethodId' => $paymentMethodId],
            ['id' => $openId, 'versionId' => $versionId, 'paymentMethodId' => $paymentMethodId, 'amount' => $amount],
        ], $event->writes['update']['order_transaction']);
    }

    public function testLeavesMergesWithoutTransactionAmountsAlone(): void
    {
        $writes = [
            'insert' => [],
            'update' => [
                'order_transaction' => [
                    ['id' => Uuid::randomHex(), 'versionId' => Uuid::randomHex(), 'customFields' => ['note' => 'call back']],
                ],
            ],
            'delete' => [],
        ];
        $event = new BeforeVersionMergeEvent($writes);

        (new OrderTransactionVersionMergeSubscriber(StaticEntityRepository::of(OrderTransactionCollection::class)))->onBeforeVersionMerge($event);

        static::assertSame($writes, $event->writes);
    }
}
