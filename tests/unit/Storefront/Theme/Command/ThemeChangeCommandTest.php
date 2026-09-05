<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\Command\ThemeChangeCommand;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Storefront\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    public function testItDeletesUnusedDirectoriesAfterChange(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())
            ->method('deleteUnusedDirectories')
            ->willReturn(2);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItSkipsCleanupWhenNoCleanupOptionIsPassed(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->never())
            ->method('deleteUnusedDirectories');

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testItCleansUpEvenWhenCompilationIsSkipped(): void
    {
        $unusedThemeDirectoryDeleter = static::createMock(UnusedThemeDirectoryDeleter::class);
        $unusedThemeDirectoryDeleter->expects($this->once())
            ->method('deleteUnusedDirectories')
            ->willReturn(0);

        $commandTester = new CommandTester($this->createCommand($unusedThemeDirectoryDeleter));

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-compile' => true]);
        $commandTester->assertCommandIsSuccessful();
    }

    private function createCommand(UnusedThemeDirectoryDeleter $unusedThemeDirectoryDeleter): ThemeChangeCommand
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier($salesChannel->getId());
        $salesChannel->setName('Storefront');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Storefront');

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]);
        /** @var StaticEntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = new StaticEntityRepository([new ThemeCollection([$theme])]);

        $command = new ThemeChangeCommand(
            static::createStub(ThemeService::class),
            static::createStub(StorefrontPluginRegistry::class),
            $salesChannelRepository,
            $themeRepository,
            $unusedThemeDirectoryDeleter
        );

        // register the command on an application so the "question" helper set is available
        (new Application())->addCommand($command);

        return $command;
    }
}
