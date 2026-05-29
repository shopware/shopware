<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTaskHandler;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class CleanupSalesChannelContextTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupSalesChannelContextTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupSalesChannelContextTaskHandler::class);
    }

    public function testCleanup(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM sales_channel_context');

        $ids = new IdsCollection();

        $this->createSalesChannelContext($ids->create('context-1'), null, true);
        $this->createSalesChannelContext($ids->create('context-2'), null, false);

        $date = new \DateTime();
        $date->modify(\sprintf('-%d day', 121));
        $this->createSalesChannelContext($ids->create('context-3'), $date, true);
        $this->createSalesChannelContext($ids->create('context-4'), $date, false);

        $this->handler->run();

        $contexts = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel_context');

        static::assertCount(2, $contexts);
        static::assertContains($ids->get('context-1'), $contexts);
        static::assertContains($ids->get('context-2'), $contexts);

        $tokenContexts = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(sales_channel_context_id)) FROM sales_channel_context_token');

        static::assertCount(1, $tokenContexts);
        static::assertContains($ids->get('context-1'), $tokenContexts);
    }

    private function createSalesChannelContext(string $id, ?\DateTime $date, bool $withToken): void
    {
        $id = Uuid::fromHexToBytes($id);
        $updatedAt = ($date ?? new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $payload = [
            'id' => $id,
            'cart_token' => CartService::getNewToken(),
            'payload' => json_encode([
                'key' => 'value',
            ]),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => $updatedAt,
        ];

        static::getContainer()->get(Connection::class)->insert('sales_channel_context', $payload);

        if (!$withToken) {
            return;
        }

        $date ??= new \DateTime();

        static::getContainer()->get(Connection::class)->insert('sales_channel_context_token', [
            'token' => SalesChannelContextService::getNewToken(),
            'sales_channel_context_id' => $id,
            'updated_at' => $updatedAt,
        ]);
    }
}
