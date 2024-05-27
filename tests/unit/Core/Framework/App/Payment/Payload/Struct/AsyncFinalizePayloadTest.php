<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\AsyncFinalizePayload;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AsyncFinalizePayload::class)]
class AsyncFinalizePayloadTest extends TestCase
{
    public function testPayload(): void
    {
        $transaction = new OrderTransactionEntity();
        $requestData = ['foo' => 'bar'];
        $recurring = new RecurringDataStruct('foo', new \DateTime());
        $source = new Source('foo', 'bar', '1.0.0');

        $payload = new AsyncFinalizePayload($transaction, $requestData, $recurring);
        $payload->setSource($source);

        static::assertEquals($transaction, $payload->getOrderTransaction());
        static::assertSame($recurring, $payload->getRecurring());
        static::assertSame($source, $payload->getSource());
    }
}
