<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Api\PaymentMethodTechnicalNameFkResolver;
use Shopware\Core\Framework\Api\Sync\FkReference;

/**
 * @internal
 */
#[CoversClass(PaymentMethodTechnicalNameFkResolver::class)]
class PaymentMethodTechnicalNameFkResolverTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('payment_method.technical_name', PaymentMethodTechnicalNameFkResolver::getName());
    }

    public function testResolve(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'payment_invoice' => 'invoice0000000000000000000000001',
                'payment_paypal' => 'paypal00000000000000000000000002',
            ]);

        $resolver = new PaymentMethodTechnicalNameFkResolver($connection);

        $references = [
            new FkReference('ops/0/paymentMethodId', 'payment_method', 'technicalName', 'payment_invoice', false),
            new FkReference('ops/1/paymentMethodId', 'payment_method', 'technicalName', 'payment_paypal', false),
            new FkReference('ops/2/paymentMethodId', 'payment_method', 'technicalName', 'payment_unknown', false),
        ];

        $result = $resolver->resolve($references);

        static::assertSame('invoice0000000000000000000000001', $result[0]->resolved);
        static::assertSame('paypal00000000000000000000000002', $result[1]->resolved);
        static::assertNull($result[2]->resolved);
    }

    public function testResolveWithEmptyInputDoesNotQuery(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $resolver = new PaymentMethodTechnicalNameFkResolver($connection);

        static::assertSame([], $resolver->resolve([]));
    }
}
