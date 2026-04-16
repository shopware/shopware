<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\IsNewDetector;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(IsNewDetector::class)]
class IsNewDetectorTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    #[DataProvider('isNewBoundaryProvider')]
    public function testIsNewBoundaryAroundConfiguredDayRange(
        string $releaseDate,
        string $now,
        int $markAsNewDayRange,
        bool $expected,
    ): void {
        $systemConfig = new StaticSystemConfigService([
            'core.listing.markAsNew' => $markAsNewDayRange,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        $product = new ProductEntity();
        $product->setReleaseDate(new \DateTimeImmutable($releaseDate));

        $detector = new IsNewDetector($systemConfig, new MockClock(new \DateTimeImmutable($now)));

        static::assertSame($expected, $detector->isNew($product, $context));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('returnsFalseProvider')]
    public function testReturnsFalse(?string $releaseDate, array $config): void
    {
        $context = static::createStub(SalesChannelContext::class);

        $product = new ProductEntity();
        if ($releaseDate !== null) {
            $product->setReleaseDate(new \DateTimeImmutable($releaseDate));
        }

        $detector = new IsNewDetector(
            new StaticSystemConfigService($config),
            new MockClock(new \DateTimeImmutable('2025-06-15T12:00:00+00:00')),
        );

        static::assertFalse($detector->isNew($product, $context));
    }

    /**
     * @return iterable<string, array{?string, array<string, mixed>}>
     */
    public static function returnsFalseProvider(): iterable
    {
        yield 'product without release date is never new' => [
            null,
            ['core.listing.markAsNew' => 30],
        ];

        yield 'returns false when mark-as-new config is missing' => [
            '2025-06-14T12:00:00+00:00',
            [],
        ];
    }

    /**
     * @return iterable<string, array{string, string, int, bool}>
     */
    public static function isNewBoundaryProvider(): iterable
    {
        $release = '2025-01-01T12:00:00+00:00';

        yield 'same instant as release date is new' => [
            $release,
            $release,
            30,
            true,
        ];

        yield 'exactly at threshold (30 full days) is still new' => [
            $release,
            '2025-01-31T12:00:00+00:00',
            30,
            true,
        ];

        yield 'thirty-one full days after release is no longer new' => [
            $release,
            '2025-02-01T12:00:00+00:00',
            30,
            false,
        ];

        yield 'day-range zero treats only same-day as new' => [
            $release,
            '2025-01-01T23:59:59+00:00',
            0,
            true,
        ];

        yield 'day-range zero rejects the next day' => [
            $release,
            '2025-01-02T12:00:00+00:00',
            0,
            false,
        ];
    }
}
