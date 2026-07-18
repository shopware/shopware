<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Command\SystemConfigureShopCommand;
use Shopware\Core\Maintenance\System\Service\ShopConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigureShopCommand::class)]
class SystemConfigureShopCommandTest extends TestCase
{
    private MockObject&ShopConfigurator $shopConfigurator;

    private MockObject&CacheClearer $cacheClearer;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->shopConfigurator = $this->createMock(ShopConfigurator::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);

        $this->commandTester = new CommandTester(new SystemConfigureShopCommand(
            $this->shopConfigurator,
            $this->cacheClearer
        ));
    }

    #[TestDox('The basic shop information is updated and the cache is cleared')]
    public function testUpdatesBasicInformation(): void
    {
        $this->shopConfigurator
            ->expects($this->once())
            ->method('updateBasicInformation')
            ->with('Test Shop', 'shop@example.com');
        $this->shopConfigurator->expects($this->never())->method('setDefaultLanguage');
        $this->shopConfigurator->expects($this->never())->method('setDefaultCurrency');
        $this->cacheClearer->expects($this->once())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--shop-name' => 'Test Shop',
            '--shop-email' => 'shop@example.com',
        ]));
        static::assertStringContainsString('Shop configured successfully', $this->commandTester->getDisplay());
    }

    #[TestDox('Default language and currency are changed without questions in non-interactive mode')]
    public function testChangesLocaleAndCurrencyNonInteractively(): void
    {
        $this->shopConfigurator->expects($this->once())->method('updateBasicInformation');
        $this->shopConfigurator->expects($this->once())->method('setDefaultLanguage')->with('de-DE');
        $this->shopConfigurator->expects($this->once())->method('setDefaultCurrency')->with('EUR');
        $this->cacheClearer->expects($this->once())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--shop-locale' => 'de-DE',
            '--shop-currency' => 'EUR',
            '--no-interaction' => true,
        ]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Successfully changed shop default language', $display);
        static::assertStringContainsString('Successfully changed shop default currency', $display);
    }

    #[TestDox('Changing the default language is aborted when the destructive-change warning is declined')]
    public function testAbortsLocaleChangeWithoutConfirmation(): void
    {
        $this->shopConfigurator->expects($this->once())->method('updateBasicInformation');
        $this->shopConfigurator->expects($this->never())->method('setDefaultLanguage');
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->commandTester->setInputs(['no']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--shop-locale' => 'de-DE',
        ]));
        static::assertStringContainsString('Aborting due to user input', $this->commandTester->getDisplay());
    }
}
