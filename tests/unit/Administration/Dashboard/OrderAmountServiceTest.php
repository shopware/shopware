<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Dashboard;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * This is basic coverage mainly about data mapping, not the queries.
 * See OrderAmountServiceTest in integration for more complex scenarios.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderAmountService::class)]
class OrderAmountServiceTest extends TestCase
{
    private CashRounding&MockObject $cashRounding;

    private Connection&MockObject $connection;

    private Context $context;

    private QueryBuilder&MockObject $queryBuilder;

    private Result&MockObject $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
        $this->cashRounding = $this->createMock(CashRounding::class);
        $this->cashRounding->method('cashRound')->willReturnCallback(static fn ($amount) => round($amount, 2));
        $this->context = Context::createDefaultContext();
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->result = $this->createMock(Result::class);
        $this->queryBuilder->method('executeQuery')->willReturn($this->result);
        $this->connection->method('createQueryBuilder')->willReturn($this->queryBuilder);
    }

    public function testLoadUnpaidReturnsMappedData(): void
    {
        $expected = [
            ['date' => '2024-01-01', 'count' => 2, 'amount' => 100.0],
        ];

        $this->result->method('fetchAllAssociative')->willReturn($expected);

        $service = new OrderAmountService($this->connection, $this->cashRounding, false);

        $result = $service->load(Context::createDefaultContext(), '2024-01-01', false);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertArrayHasKey('date', $result[0]);
        static::assertSame('2024-01-01', $result[0]['date']);
        static::assertSame(2, $result[0]['count']);
        static::assertArrayHasKey('count', $result[0]);
        static::assertSame(100.0, $result[0]['amount']);
        static::assertArrayHasKey('amount', $result[0]);
    }

    public function testLoadPaidReturnsMappedData(): void
    {
        $expected = [
            ['date' => '2024-01-02', 'count' => 1, 'amount' => 50.0],
        ];

        $this->result->method('fetchAllAssociative')->willReturn($expected);
        $this->connection->method('fetchOne')->willReturn('paid-id');

        $service = new OrderAmountService($this->connection, $this->cashRounding, true);

        $result = $service->load($this->context, '2024-01-01', true);

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertArrayHasKey('amount', $result[0]);
        static::assertSame(50.0, $result[0]['amount']);
        static::assertArrayHasKey('date', $result[0]);
        static::assertSame('2024-01-02', $result[0]['date']);
        static::assertArrayHasKey('count', $result[0]);
        static::assertSame(1, $result[0]['count']);
    }
}
