<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayload;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentPayload::class)]
class PaymentPayloadTest extends TestCase
{
    public function testPayload(): void
    {
        $transaction = new OrderTransactionEntity();
        $order = new OrderEntity();
        $returnUrl = 'https://foo.bar';
        $requestData = ['foo' => 'bar'];
        $validateStruct = new ArrayStruct();
        $recurring = new RecurringDataStruct('foo', new \DateTime());
        $source = new Source('foo', 'bar', '1.0.0');

        $payload = new PaymentPayload($transaction, $order, $requestData, $returnUrl, $validateStruct, $recurring);
        $payload->setSource($source);

        static::assertEquals($transaction, $payload->getOrderTransaction());
        static::assertSame($order, $payload->getOrder());
        static::assertSame($returnUrl, $payload->getReturnUrl());
        static::assertSame($requestData, $payload->getRequestData());
        static::assertSame($validateStruct, $payload->getValidateStruct());
        static::assertSame($recurring, $payload->getRecurring());
        static::assertSame($source, $payload->getSource());

        if (!Feature::isActive('v6.7.0.0')) {
            static::assertSame($requestData, $payload->jsonSerialize()['queryParameters']);
        }
    }
}
