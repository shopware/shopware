<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl as AppUrlFingerprint;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(AppUrlVerificationPrinter::class)]
class AppUrlVerificationPrinterTest extends TestCase
{
    public function testPrintsPassBasic(): void
    {
        $shopId = ShopId::v2('shop-id', [AppUrlFingerprint::IDENTIFIER => 'https://example.com']);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willReturn($shopId);

        $printer = new AppUrlVerificationPrinter($shopIdProvider);

        $output = new BufferedOutput();
        $io = new ShopwareStyle(new ArrayInput([]), $output);

        $state = new VerificationState(
            VerificationStatus::PASS,
            1,
            new \DateTimeImmutable('2024-01-01 12:00:00'),
            null,
        );

        $printer->print($io, $state, false);

        $display = $output->fetch();

        static::assertStringContainsString('App URL Verification Status', $display);
        static::assertStringContainsString('APP URL: https://example.com', $display);
        static::assertStringContainsString('Result', $display);
        static::assertStringContainsString('OK', $display);
        static::assertStringContainsString('Info', $display);
        static::assertStringContainsString('No additional information available', $display);
        static::assertStringContainsString('Tries', $display);
        static::assertStringContainsString('1', $display);
        static::assertStringContainsString('Checked at', $display);
        static::assertStringContainsString('2024-01-01 12:00:00', $display);
        static::assertStringContainsString('When a hard fail occurs, app communication will be disabled.', $display);
    }

    public function testPrintsManualAttemptAndTimestamp(): void
    {
        $shopId = ShopId::v2('shop-id', [AppUrlFingerprint::IDENTIFIER => 'https://example.org']);
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willReturn($shopId);

        $printer = new AppUrlVerificationPrinter($shopIdProvider);

        $output = new BufferedOutput();
        $io = new ShopwareStyle(new ArrayInput([]), $output);

        $state = new VerificationState(
            VerificationStatus::SOFT_FAIL,
            3,
            new \DateTimeImmutable('2025-01-01 12:00:00'),
            'Temporary error',
        );

        $printer->print($io, $state, true);

        $display = $output->fetch();

        static::assertStringContainsString('APP URL: https://example.org', $display);
        static::assertStringContainsString('SOFT FAIL', $display);
        static::assertStringContainsString('please try again', $display);
        static::assertStringContainsString('Manual attempt', $display);
        static::assertStringContainsString('2025-01-01 12:00:00', $display);
        static::assertStringContainsString('Temporary error', $display);
    }
}
