<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\AppUrlVerifyCommand;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUrlVerifyCommand::class)]
class AppUrlVerifyCommandTest extends TestCase
{
    public function testVerifyCallsForceVerify(): void
    {
        $shopId = ShopId::v2('shop-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())
            ->method('getShopIdWithoutVerification')
            ->willReturn($shopId);

        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->with($shopId);

        $command = new AppUrlVerifyCommand($shopIdProvider, $verifier);
        $statusCmd = new Command('app:url:status');
        $statusCmd->setCode(fn () => Command::SUCCESS);

        $app = new Application();
        $app->add($statusCmd);
        $command->setApplication($app);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::SUCCESS, $exitCode);
    }
}
