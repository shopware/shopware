<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\AppUrlVerificationStatusCommand;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
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
#[CoversClass(AppUrlVerificationStatusCommand::class)]
class AppUrlVerificationStatusCommandTest extends TestCase
{
    /**
     * @var ShopIdProvider&MockObject
     */
    private ShopIdProvider $shopIdProvider;

    /**
     * @var AppUrlVerifier&MockObject
     */
    private AppUrlVerifier $verifier;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->verifier = $this->createMock(AppUrlVerifier::class);

        $printer = new AppUrlVerificationPrinter($this->shopIdProvider);
        $command = new AppUrlVerificationStatusCommand($this->verifier, $printer);
        $this->tester = new CommandTester($command);
    }

    public function testNoStateShowsWarningAndSucceeds(): void
    {
        $this->verifier->method('getCurrentState')->willReturn(null);

        $exit = $this->tester->execute([]);

        $output = $this->tester->getDisplay();
        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('No verification state found.', $output);
    }

    public function testPassStateShowsOkAndDetails(): void
    {
        $state = new VerificationState(
            VerificationStatus::PASS,
            1,
            new \DateTimeImmutable('2025-01-01 12:00:00', new \DateTimeZone('UTC')),
            null
        );

        $this->verifier->method('getCurrentState')->willReturn($state);

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://example.com']);
        $this->shopIdProvider->method('getShopId')->willReturn($shopId);

        $exit = $this->tester->execute([]);

        $output = $this->tester->getDisplay();

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('App URL Verification Status', $output);
        static::assertStringContainsString('APP URL: https://example.com', $output);

        static::assertStringContainsString('Result', $output);
        static::assertStringContainsString('OK', $output);
        static::assertStringContainsString('Info', $output);
        static::assertStringContainsString('No additional information available', $output);
        static::assertStringContainsString('Tries', $output);
        static::assertStringContainsString('1', $output);
        static::assertStringContainsString('Checked at', $output);
        static::assertMatchesRegularExpression('/Checked at.*2025-01-01 12:00:00/', $output);
    }

    public function testSoftFailState(): void
    {
        $state = new VerificationState(
            VerificationStatus::SOFT_FAIL,
            2,
            new \DateTimeImmutable('2025-06-15 08:30:00', new \DateTimeZone('UTC')),
            'transient error'
        );

        $this->verifier->method('getCurrentState')->willReturn($state);

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://www.example.com']);
        $this->shopIdProvider->method('getShopId')->willReturn($shopId);

        $exit = $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('APP URL: https://www.example.com', $output);
        static::assertStringContainsString('Result', $output);
        static::assertStringContainsString('SOFT FAIL', $output);
        static::assertStringContainsString('please try again', $output);
        static::assertStringContainsString('Info', $output);
        static::assertStringContainsString('transient error', $output);
        static::assertStringContainsString('Tries', $output);
        static::assertStringContainsString('2', $output);
        static::assertMatchesRegularExpression('/Checked at.*2025-06-15 08:30:00/', $output);
    }

    public function testHardFailState(): void
    {
        $state = new VerificationState(
            VerificationStatus::HARD_FAIL,
            3,
            new \DateTimeImmutable('2025-09-01 17:45:00', new \DateTimeZone('UTC')),
            'bad url'
        );

        $this->verifier->method('getCurrentState')->willReturn($state);

        $shopId = ShopId::v2('shop-id', [AppUrl::IDENTIFIER => 'https://broken.example']);
        $this->shopIdProvider->method('getShopId')->willReturn($shopId);

        $exit = $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('APP URL: https://broken.example', $output);
        static::assertStringContainsString('HARD FAIL', $output);
        static::assertStringContainsString('APP_URL is incorrect or not reachable', $output);
        static::assertStringContainsString('bad url', $output);
        static::assertStringContainsString('Tries', $output);
        static::assertStringContainsString('3', $output);
        static::assertMatchesRegularExpression('/Checked at.*2025-09-01 17:45:00/', $output);
    }
}
