<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Command\AppUrlVerifyCommand;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUrlVerifyCommand::class)]
class AppUrlVerifyCommandTest extends TestCase
{
    public function testAPendingShopIdChangeIsReportedAndTheAppUrlIsStillVerified(): void
    {
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willThrowException(AppException::shopIdChangeSuggested(
            ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://old.example.com']),
            new FingerprintComparisonResult([], [], 75),
        ));

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->willReturn(new VerificationState(
                'https://new.example.com',
                VerificationStatus::PASS,
                1,
                new \DateTimeImmutable('2025-01-01 12:00:00', new \DateTimeZone('UTC')),
                info: null,
                statusCode: null,
            ));

        $tester = new CommandTester(new AppUrlVerifyCommand($shopIdProvider, $verifier, new AppUrlVerificationPrinter()));

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        static::assertStringContainsString('Shop ID change suggested. Run "bin/console app:shop-id:change"', $display);
        static::assertStringContainsString('APP URL: https://new.example.com', $display);
        static::assertStringContainsString('OK', $display);
    }

    public function testVerifyPrintsOutput(): void
    {
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willReturn(ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']));

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->willReturn(new VerificationState(
                'https://example.com',
                VerificationStatus::HARD_FAIL,
                1,
                new \DateTimeImmutable('2025-01-01 12:00:00', new \DateTimeZone('UTC')),
                'Unexpected response from APP_URL verification endpoint: HTTP 404. not found',
                statusCode: null,
            ));

        $tester = new CommandTester(new AppUrlVerifyCommand($shopIdProvider, $verifier, new AppUrlVerificationPrinter()));

        static::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        static::assertStringNotContainsString('Shop ID change suggested', $display);
        static::assertStringContainsString('App URL Verification Status', $display);
        static::assertStringContainsString('APP URL: https://example.com', $display);
        static::assertStringContainsString('Result', $display);
        static::assertStringContainsString('HARD FAIL', $display);
        static::assertStringContainsString('APP_URL is incorrect or not reachable', $display);
        static::assertStringContainsString('Info', $display);
        static::assertStringContainsString('Unexpected response from APP_URL verification endpoint', $display);
        static::assertStringContainsString('Tries', $display);
        static::assertStringContainsString('Manual attempt', $display);
        static::assertStringContainsString('Checked at', $display);
        static::assertStringContainsString('2025-01-01 12:00:00 UTC', $display);
        static::assertStringContainsString('When a hard fail occurs, app communication will be disabled.', $display);
    }
}
