<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchaseRegistry;

/**
 * @internal
 */
#[CoversClass(InAppPurchaseRegistry::class)]
#[Package('checkout')]
class InAppPurchaseRegistryTest extends TestCase
{
    public function testCompilerPass(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'active-feature-1' => 'extension-1',
                'active-feature-2' => 'extension-1',
                'active-feature-3' => 'extension-2',
            ]);

        $registry = new InAppPurchaseRegistry($connection);
        $registry->register();

        static::assertTrue(InAppPurchase::isActive('active-feature-1'));
        static::assertTrue(InAppPurchase::isActive('active-feature-2'));
        static::assertTrue(InAppPurchase::isActive('active-feature-3'));
        static::assertFalse(InAppPurchase::isActive('this-one-is-not'));

        static::assertSame(['active-feature-1', 'active-feature-2', 'active-feature-3'], InAppPurchase::all());
        static::assertSame(['active-feature-1', 'active-feature-2'], InAppPurchase::getByExtension('extension-1'));
        static::assertSame(['active-feature-3'], InAppPurchase::getByExtension('extension-2'));
        static::assertSame([], InAppPurchase::getByExtension('extension-3'));

        InAppPurchase::reset();
    }

    public function testConnectionError(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willThrowException(new ConnectionException());

        $registry = new InAppPurchaseRegistry($connection);
        $registry->register();

        static::assertEmpty(InAppPurchase::all());
    }
}
