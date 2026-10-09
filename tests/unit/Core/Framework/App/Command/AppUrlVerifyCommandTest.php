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
    public function testVerifiesTheUrlStoredWithTheShopId(): void
    {
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->exactly(2))
            ->method('getShopId')
            ->willReturn($shopId);

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with('https://example.com')
            ->willReturn(new VerificationState(
                VerificationStatus::PASS,
                1,
                new \DateTimeImmutable('2025-01-01 12:00:00', new \DateTimeZone('UTC')),
                null,
            ));

        $printer = new AppUrlVerificationPrinter($shopIdProvider);
        $command = new AppUrlVerifyCommand($shopIdProvider, $verifier, $printer);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testFailsWhileAShopIdChangeIsPending(): void
    {
        $shopIdChange = AppException::shopIdChangeSuggested(
            ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://old.example.com']),
            new FingerprintComparisonResult([], [], 75),
        );

        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')->willThrowException($shopIdChange);

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->never())->method('forceVerify');

        $command = new AppUrlVerifyCommand($shopIdProvider, $verifier, new AppUrlVerificationPrinter($shopIdProvider));

        $this->expectExceptionObject($shopIdChange);

        (new CommandTester($command))->execute([]);
    }

    public function testVerifyPrintsOutput(): void
    {
        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->exactly(2))
            ->method('getShopId')
            ->willReturn($shopId);

        $verifier = $this->createMock(AppUrlVerifier::class);
        $state = new VerificationState(
            VerificationStatus::HARD_FAIL,
            1,
            new \DateTimeImmutable('2025-01-01 12:00:00', new \DateTimeZone('UTC')),
            'Unexpected response from APP_URL verification endpoint: HTTP 404. not found',
        );

        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with('https://example.com')
            ->willReturn($state);

        $printer = new AppUrlVerificationPrinter($shopIdProvider);
        $command = new AppUrlVerifyCommand($shopIdProvider, $verifier, $printer);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);

        $display = $tester->getDisplay();

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
