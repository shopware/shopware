<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\IsNewDetector;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(IsNewDetector::class)]
#[Package('inventory')]
class IsNewDetectorTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0188adcb6b8f7e4ba6c2a2f6e8c5a000';

    #[DataProvider('boundaryCases')]
    public function testIsNewBoundaryAroundConfiguredDayRange(
        \DateTimeImmutable $releaseDate,
        \DateTimeImmutable $now,
        int $markAsNewDayRange,
        bool $expected,
    ): void {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')
            ->with('core.listing.markAsNew', self::SALES_CHANNEL_ID)
            ->willReturn($markAsNewDayRange);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        $product = new ProductEntity();
        $product->setReleaseDate($releaseDate);

        $detector = new IsNewDetector($systemConfig, new MockClock($now));

        static::assertSame($expected, $detector->isNew($product, $context));
    }

    public function testProductWithoutReleaseDateIsNeverNew(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(30);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        $product = new ProductEntity();
        // releaseDate intentionally left null

        $detector = new IsNewDetector(
            $systemConfig,
            new MockClock(new \DateTimeImmutable('2025-06-15T12:00:00+00:00')),
        );

        static::assertFalse($detector->isNew($product, $context));
    }

    /**
     * @return iterable<string, array{\DateTimeImmutable, \DateTimeImmutable, int, bool}>
     */
    public static function boundaryCases(): iterable
    {
        $release = new \DateTimeImmutable('2025-01-01T12:00:00+00:00');

        yield 'same instant as release date is new' => [
            $release,
            $release,
            30,
            true,
        ];

        yield 'one second before threshold is new' => [
            $release,
            new \DateTimeImmutable('2025-01-31T11:59:59+00:00'),
            30,
            true,
        ];

        yield 'exactly at threshold (30 full days) is still new' => [
            $release,
            new \DateTimeImmutable('2025-01-31T12:00:00+00:00'),
            30,
            true,
        ];

        yield 'one second after threshold is still new (30 days, 1 second)' => [
            $release,
            new \DateTimeImmutable('2025-01-31T12:00:01+00:00'),
            30,
            true,
        ];

        yield 'thirty-one full days after release is no longer new' => [
            $release,
            new \DateTimeImmutable('2025-02-01T12:00:00+00:00'),
            30,
            false,
        ];

        yield 'day-range zero treats only same-day as new' => [
            $release,
            new \DateTimeImmutable('2025-01-01T23:59:59+00:00'),
            0,
            true,
        ];

        yield 'day-range zero rejects the next day' => [
            $release,
            new \DateTimeImmutable('2025-01-02T12:00:00+00:00'),
            0,
            false,
        ];
    }
}
