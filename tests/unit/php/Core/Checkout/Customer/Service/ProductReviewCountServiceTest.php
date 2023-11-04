<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Service\ProductReviewCountService
 */
class ProductReviewCountServiceTest extends TestCase
{
    private ProductReviewCountService $productReviewCountService;

    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->productReviewCountService = new ProductReviewCountService($this->connection);
    }

    public function testUpdateReviewCountWithInvalidReviewIds(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->expects(static::once())->method('executeQuery')->willReturn($result);
        $this->connection->expects(static::never())->method('prepare');

        $this->productReviewCountService->updateReviewCount([], true);
    }

    public function testUpdateReviewCount(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['customer_id' => 'foobar'],
            ['customer_id' => 'barfoo'],
        ]);

        $this->connection->expects(static::once())->method('executeQuery')->willReturn($result);
        $this->connection->expects(static::exactly(2))->method('prepare');

        $this->productReviewCountService->updateReviewCount([], true);
    }
}
