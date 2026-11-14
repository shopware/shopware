<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\PluginDebugModeCommand;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginDebugModeCommand::class)]
class PluginDebugModeCommandTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PluginCollection>
     */
    private StaticEntityRepository $pluginRepository;

    private MockObject&PluginLifecycleService $pluginLifecycleService;

    private MockObject&CacheClearer $cacheClearer;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->pluginRepository = StaticEntityRepository::of(PluginCollection::class);
        $this->pluginLifecycleService = $this->createMock(PluginLifecycleService::class);
        $this->cacheClearer = $this->createMock(CacheClearer::class);

        $this->commandTester = new CommandTester(new PluginDebugModeCommand(
            $this->pluginRepository,
            $this->pluginLifecycleService,
            $this->cacheClearer,
        ));
    }

    #[TestDox('Third-party plugins are deactivated, Shopware plugins are kept')]
    public function testDeactivatesOnlyThirdPartyPlugins(): void
    {
        $thirdPartyPlugin = $this->createPlugin(name: 'AcmePlugin', author: 'Acme');

        $this->pluginRepository->addSearch(new PluginCollection([
            $thirdPartyPlugin,
            $this->createPlugin(name: 'SwagPlugin', author: 'swag'),
            $this->createPlugin(name: 'ShopwarePlugin', author: 'shopware AG'),
            $this->createPlugin(name: 'ShopwareAgencyPlugin', author: 'Shopware'),
        ]));

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('deactivatePlugin')
            ->with($thirdPartyPlugin, static::isInstanceOf(Context::class))
            ->willReturn(static::createStub(DeactivateContext::class));

        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--force' => true]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Deactivated 1 plugin(s).', $display);
        static::assertStringContainsString('cache:clear', $display);
    }

    #[TestDox('The uninstall option uninstalls third-party plugins and keeps their user data')]
    public function testUninstallKeepsUserData(): void
    {
        $thirdPartyPlugin = $this->createPlugin(name: 'AcmePlugin', author: 'Acme');

        $this->pluginRepository->addSearch(new PluginCollection([$thirdPartyPlugin]));

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('uninstallPlugin')
            ->with($thirdPartyPlugin, static::isInstanceOf(Context::class), true)
            ->willReturn(static::createStub(UninstallContext::class));

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--uninstall' => true,
            '--force' => true,
        ]));

        static::assertStringContainsString('Uninstalled 1 plugin(s).', $this->commandTester->getDisplay());
    }

    #[TestDox('Only active plugins are searched when plugins are deactivated')]
    public function testOnlyActivePluginsAreSearched(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection());

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->commandTester->execute(['--force' => true]);

        $filters = $this->pluginRepository->searchedCriteria[0]->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(EqualsFilter::class, $filters[0]);
        static::assertSame('active', $filters[0]->getField());
        static::assertTrue($filters[0]->getValue());
    }

    #[TestDox('Only installed plugins are searched when plugins are uninstalled')]
    public function testOnlyInstalledPluginsAreSearchedForUninstall(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection());

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->commandTester->execute(['--uninstall' => true, '--force' => true]);

        $filters = $this->pluginRepository->searchedCriteria[0]->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(NotFilter::class, $filters[0]);

        $innerFilters = $filters[0]->getQueries();
        static::assertCount(1, $innerFilters);
        static::assertInstanceOf(EqualsFilter::class, $innerFilters[0]);
        static::assertSame('installedAt', $innerFilters[0]->getField());
        static::assertNull($innerFilters[0]->getValue());
    }

    #[TestDox('Nothing happens when all plugins are from Shopware')]
    public function testReportsWhenNoThirdPartyPluginExists(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection([
            $this->createPlugin(name: 'ShopwarePlugin', author: 'shopware AG'),
        ]));

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->pluginLifecycleService->expects($this->never())->method('uninstallPlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));

        static::assertStringContainsString(
            'No plugins found which are not from Shopware.',
            $this->commandTester->getDisplay()
        );
    }

    #[TestDox('A plugin without an author is treated as a third-party plugin')]
    public function testPluginWithoutAuthorIsTreatedAsThirdParty(): void
    {
        $plugin = $this->createPlugin(name: 'NoAuthorPlugin', author: null);

        $this->pluginRepository->addSearch(new PluginCollection([$plugin]));

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('deactivatePlugin')
            ->with($plugin, static::isInstanceOf(Context::class))
            ->willReturn(static::createStub(DeactivateContext::class));

        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--force' => true]));
    }

    #[TestDox('A plugin with a Shopware composer package name is kept, regardless of its author')]
    public function testPluginWithShopwareComposerNameIsKept(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection([
            $this->createPlugin(name: 'ShopwareTestPlugin', author: 'John Doe', composerName: 'shopware/test-plugin'),
        ]));

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--force' => true]));

        static::assertStringContainsString(
            'No plugins found which are not from Shopware.',
            $this->commandTester->getDisplay()
        );
    }

    #[TestDox('A failing plugin does not stop the remaining plugins from being deactivated')]
    public function testContinuesWhenAPluginCannotBeDeactivated(): void
    {
        $brokenPlugin = $this->createPlugin(name: 'BrokenPlugin', author: 'Acme');
        $workingPlugin = $this->createPlugin(name: 'WorkingPlugin', author: 'Acme');
        $dependentPlugin = $this->createPlugin(name: 'DependentPlugin', author: 'Acme');

        $this->pluginRepository->addSearch(new PluginCollection([$brokenPlugin, $workingPlugin]));

        $this->pluginLifecycleService
            ->expects($this->exactly(2))
            ->method('deactivatePlugin')
            ->willReturnCallback(static function (PluginEntity $plugin) use ($dependentPlugin): DeactivateContext {
                if ($plugin->getName() === 'BrokenPlugin') {
                    throw PluginException::hasActiveDependants('BrokenPlugin', [$dependentPlugin]);
                }

                return static::createStub(DeactivateContext::class);
            });

        $this->cacheClearer->expects($this->never())->method('clear');

        static::assertSame(Command::FAILURE, $this->commandTester->execute(['--force' => true]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('Plugin "WorkingPlugin" has been deactivated successfully.', $display);
        static::assertStringContainsString('1 plugin(s) could not be deactivated:', $display);
        static::assertStringContainsString('BrokenPlugin:', $display);
    }

    #[TestDox('The command does not change any plugin when the confirmation is declined')]
    public function testAbortsWithoutConfirmation(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection([
            $this->createPlugin(name: 'AcmePlugin', author: 'Acme'),
        ]));

        $this->pluginLifecycleService->expects($this->never())->method('deactivatePlugin');
        $this->cacheClearer->expects($this->never())->method('clear');

        $this->commandTester->setInputs(['no']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([], ['interactive' => true]));

        static::assertStringContainsString('Aborted.', $this->commandTester->getDisplay());
    }

    #[TestDox('The command changes the plugins after the confirmation was accepted')]
    public function testActsAfterConfirmation(): void
    {
        $plugin = $this->createPlugin(name: 'AcmePlugin', author: 'Acme');

        $this->pluginRepository->addSearch(new PluginCollection([$plugin]));

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('deactivatePlugin')
            ->willReturn(static::createStub(DeactivateContext::class));

        $this->cacheClearer->expects($this->never())->method('clear');

        $this->commandTester->setInputs(['yes']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([], ['interactive' => true]));
    }

    #[TestDox('The cache is cleared when the clearCache option is set')]
    public function testClearsCacheWhenRequested(): void
    {
        $this->pluginRepository->addSearch(new PluginCollection([
            $this->createPlugin(name: 'AcmePlugin', author: 'Acme'),
        ]));

        $this->pluginLifecycleService
            ->expects($this->once())
            ->method('deactivatePlugin')
            ->willReturn(static::createStub(DeactivateContext::class));

        $this->cacheClearer->expects($this->once())->method('clear');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([
            '--force' => true,
            '--clearCache' => true,
        ]));

        static::assertStringContainsString('Cache cleared', $this->commandTester->getDisplay());
    }

    private function createPlugin(
        string $name,
        ?string $author,
        ?string $composerName = null,
    ): PluginEntity {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName($name);
        $plugin->setLabel($name);
        $plugin->setVersion('1.0.0');
        $plugin->setActive(true);

        if ($author !== null) {
            $plugin->setAuthor($author);
        }

        if ($composerName !== null) {
            $plugin->setComposerName($composerName);
        }

        $plugin->setInstalledAt(new \DateTimeImmutable());

        return $plugin;
    }
}
