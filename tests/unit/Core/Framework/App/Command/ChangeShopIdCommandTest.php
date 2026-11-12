<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\ChangeShopIdCommand;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeShopIdCommand::class)]
class ChangeShopIdCommandTest extends TestCase
{
    private ShopIdProvider&Stub $shopIdProvider;

    protected function setUp(): void
    {
        $this->shopIdProvider = static::createStub(ShopIdProvider::class);
        $this->shopIdProvider->method('verifyShopId')->willReturn(Result::ok());
    }

    public function testDoesNotChangeTheShopIdWhenTheCachedShopIdDiffersFromTheDatabase(): void
    {
        $resolver = $this->createMock(Resolver::class);
        $resolver->expects($this->never())->method('resolve');

        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('verifyShopId')->willReturn(Result::failed('The cached shop ID differs.'));

        $commandTester = new CommandTester(new ChangeShopIdCommand($resolver, $shopIdProvider));

        $commandTester->execute(['strategy' => 'testStrategy']);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('The cached shop ID differs.', $commandTester->getDisplay());
    }

    public function testChoosesTheRightStrategyForChangingTheShopId(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeShopIdCommand($urlChangeStrategy, $this->shopIdProvider)
        );

        $commandTester->setInputs(['testStrategy']);
        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('Choose what strategy should be applied when changing the shop ID?', $commandTester->getDisplay());
        static::assertStringContainsString('testStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('secondStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }

    public function testChangeShopIdWithProvidedStrategy(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeShopIdCommand($urlChangeStrategy, $this->shopIdProvider)
        );

        $commandTester->execute(['strategy' => 'testStrategy']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }

    public function testFailsIfChosenStrategyDoesNotExist(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeShopIdCommand($urlChangeStrategy, $this->shopIdProvider)
        );

        $commandTester->setInputs(['testStrategy']);
        $commandTester->execute(['strategy' => 'doesNotExist']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[NOTE] Strategy with name: "doesNotExist" not found.', $commandTester->getDisplay());
        static::assertStringContainsString('Choose what strategy should be applied when changing the shop ID?', $commandTester->getDisplay());
        static::assertStringContainsString('testStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('secondStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }
}
