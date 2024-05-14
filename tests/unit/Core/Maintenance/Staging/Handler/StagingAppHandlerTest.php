<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingAppHandler::class)]
class StagingAppHandlerTest extends TestCase
{
    public function testDeletion(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 'app_id', 'integration_id' => 'integration_id', 'name' => 'test'],
            ]);

        $tables = [];
        $ids = [];

        $connection
            ->method('delete')
            ->willReturnCallback(function (string $table, array $criteria) use (&$tables, &$ids): void {
                $tables[] = $table;
                $ids[] = $criteria['id'];
            });

        $configService = new StaticSystemConfigService();
        $configService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, 'test');

        $handler = new StagingAppHandler($connection, $configService);
        $handler->__invoke(new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class)));

        static::assertNull($configService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));

        static::assertEquals(['app', 'integration'], $tables);
        static::assertEquals(['app_id', 'integration_id'], $ids);
    }
}
