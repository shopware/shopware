<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(ProductDetailReadinessCheck::class)]
class ProductDetailReadinessCheckTest extends TestCase
{
    private KernelInterface&MockObject $kernel;

    private Connection&MockObject $connection;

    private SalesChannelDomainUtil&MockObject $util;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->kernel = $this->createMock(KernelInterface::class);

        $this->initUtilMock();
    }

    public function testName(): void
    {
        $check = $this->createCheck();
        static::assertSame('ProductDetailReadiness', $check->name());
    }

    public function testCategory(): void
    {
        $check = $this->createCheck();
        static::assertSame('FEATURE', $check->category()->name);
    }

    public function testAllowedToRunIn(): void
    {
        $check = $this->createCheck();
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }

    public function testRunSuccessfully(): void
    {
        $this->initConnectionMock();

        $this->kernel->method('handle')->willReturn(new Response());

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('Product detail pages are OK for provided sales channels.', $result->message);
        static::assertSame('OK', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(200, $result->extra[0]['responseCode']);
        static::assertSame(200, $result->extra[1]['responseCode']);

        // simple concatenated visualization of the generated URLs
        static::assertSame('http://localhost:8000/defrontend.detail.page', $result->extra[0]['storeFrontUrl']);
        static::assertSame('http://localhost:8000/enfrontend.detail.page', $result->extra[1]['storeFrontUrl']);
    }

    public function testRunSkipped(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->initCreateEmptyResult();

        $check = $this->createCheck();
        $result = $check->run();

        static::assertTrue($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('No sales channels with product detail pages found.', $result->message);
        static::assertSame('SKIPPED', $result->status->name);
        static::assertCount(0, $result->extra);
    }

    public function testRunFailed(): void
    {
        $this->initConnectionMock();
        $this->kernel->method('handle')
            ->willReturn(new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));

        $check = $this->createCheck();
        $result = $check->run();

        static::assertFalse($result->healthy);
        static::assertSame('ProductDetailReadiness', $result->name);
        static::assertSame('Some or all product detail pages are unhealthy.', $result->message);
        static::assertSame('FAILURE', $result->status->name);
        static::assertCount(2, $result->extra);

        static::assertSame(500, $result->extra[0]['responseCode']);
        static::assertSame(500, $result->extra[1]['responseCode']);
    }

    private function createCheck(): ProductDetailReadinessCheck
    {
        return new ProductDetailReadinessCheck($this->kernel, $this->util, $this->connection);
    }

    private function initUtilMock(): void
    {
        $this->util = $this->createMock(SalesChannelDomainUtil::class);
        $this->util->method('runAsSalesChannelRequest')
            ->willReturnCallback(function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('runWhileTrustingAllHosts')
            ->willReturnCallback(function (callable $callback): mixed {
                return $callback();
            });

        $this->util->method('generateDomainUrl')->willReturnCallback(function ($domain, $routeName) {
            return $domain . $routeName;
        });
    }

    private function initConnectionMock(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                ['id' => 'sales-channel-1', 'url' => 'http://localhost:8000/de'],
                ['id' => 'sales-channel-2', 'url' => 'http://localhost:8000/en'],
                ['id' => 'sales-channel-3', 'url' => 'http://localhost:8000/invalid'],
            ],
            [
                ['id' => 'sales-channel-1', 'product_id' => Uuid::randomHex()],
                ['id' => 'sales-channel-2', 'product_id' => Uuid::randomHex()],
            ]
        );
    }

    private function initCreateEmptyResult(): void
    {
        $this->util->method('createEmptyResult')
            ->with('ProductDetailReadiness', 'No sales channels with product detail pages found.')
            ->willReturn(new Result(
                'ProductDetailReadiness',
                Status::SKIPPED,
                'No sales channels with product detail pages found.',
                true,
                []
            ));
    }
}
