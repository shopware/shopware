<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingAppHandler::class)]
class StagingAppHandlerTest extends TestCase
{
    public function testDeletion(): void
    {
        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 'app_id', 'integration_id' => 'integration_id', 'name' => 'test'],
            ]);

        $order = [];
        $tables = [];
        $ids = [];

        $connection
            ->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria) use (&$order, &$tables, &$ids): int {
                $order[] = 'delete:' . $table;
                $tables[] = $table;
                $ids[] = $criteria['id'];

                return 1;
            });

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())
            ->method('deleteShopId')
            ->willReturnCallback(static function () use (&$order): void {
                $order[] = 'deleteShopId';
            });

        $handler = new StagingAppHandler($connection, $shopIdProvider);
        $handler->__invoke(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            []
        ));

        // the ShopId check that decides secret retention needs the app rows, so the ShopId goes first
        static::assertSame(['deleteShopId', 'delete:app', 'delete:integration'], $order);
        static::assertSame(['app', 'integration'], $tables);
        static::assertSame(['app_id', 'integration_id'], $ids);
    }
}
