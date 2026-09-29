<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeSalesChannel;
use Shopware\Storefront\Theme\ThemeSalesChannelCollection;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeLifecycleHandler::class)]
class ThemeLifecycleHandlerTest extends TestCase
{
    private ThemeService&Stub $themeServiceMock;

    private StorefrontPluginRegistry&Stub $configurationRegistryMock;

    private ThemeLifecycleService&Stub $themeLifecycleServiceMock;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepositoryMock;

    private Connection&Stub $connectionMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private Context $context;

    protected function setUp(): void
    {
        $this->themeServiceMock = static::createStub(ThemeService::class);
        $this->configurationRegistryMock = static::createStub(StorefrontPluginRegistry::class);
        $this->themeLifecycleServiceMock = static::createStub(ThemeLifecycleService::class);
        $this->themeRepositoryMock = static::createStub(EntityRepository::class);
        $this->connectionMock = static::createStub(Connection::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            $this->themeLifecycleServiceMock,
            $this->themeServiceMock,
            $this->themeRepositoryMock,
            $this->configurationRegistryMock,
            $this->connectionMock
        );

        $this->context = Context::createDefaultContext();
    }

    public function testThemeUninstallWithoutData(): void
    {
        $themeConfig = new StorefrontPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $collection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $configurationRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $configurationRegistryMock->expects($this->once())->method('getConfigurations')->willReturn(
            $collection
        );

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects($this->never())->method('upsert');

        $this->buildHandler(
            themeRepository: $themeRepositoryMock,
            configurationRegistry: $configurationRegistryMock
        )->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testThemeUninstallWithDependendThemes(): void
    {
        $themeConfig = new StorefrontPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $collection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $configurationRegistryMock = $this->createMock(StorefrontPluginRegistry::class);
        $configurationRegistryMock->expects($this->once())->method('getConfigurations')->willReturn(
            $collection
        );

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturn([
            [
                'id' => $themeId,
                'dependentId' => Uuid::randomHex(),
            ],
            [
                'id' => $themeId,
                'dependentId' => Uuid::randomHex(),
            ],
        ]);

        $themeRepositoryMock = $this->createMock(EntityRepository::class);
        $themeRepositoryMock->expects($this->once())->method('upsert');

        $this->buildHandler(
            themeRepository: $themeRepositoryMock,
            configurationRegistry: $configurationRegistryMock
        )->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testAssignmentException(): void
    {
        $themeConfig = new StorefrontPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
            ],
            [
                [
                    'id' => $themeId,
                    'themeName' => 'Simple Theme',
                    'dthemeName' => 'Dependent On Simple Theme',
                    'dependentId' => Uuid::randomHex(),
                    'saleschannelId' => Uuid::randomHex(),
                    'saleschannelName' => 'SalesChannelName1',
                    'dsaleschannelId' => Uuid::randomHex(),
                    'dsaleschannelName' => 'SalesChannelName2',
                ],
                [
                    'id' => $themeId,
                    'themeName' => 'Simple Theme',
                    'dthemeName' => 'Dependent On Simple Theme',
                    'dependentId' => Uuid::randomHex(),
                    'saleschannelId' => Uuid::randomHex(),
                    'saleschannelName' => 'SalesChannelName1',
                    'dsaleschannelId' => Uuid::randomHex(),
                    'dsaleschannelName' => 'SalesChannelName2',
                ],
            ]
        );

        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(
            new ThemeSalesChannelCollection(
                [
                    new ThemeSalesChannel(Uuid::randomHex(), Uuid::randomHex()),
                ]
            )
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ThemeAssignmentException::class);
        } else {
            $this->expectException(ThemeException::class);
        }
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');

        $this->themeLifecycleHandler->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testAssignmentExceptionInException(): void
    {
        $themeConfig = new StorefrontPluginConfiguration('SimpleTheme');
        $themeConfig->setStyleFiles(new FileCollection());
        $themeConfig->setScriptFiles(new FileCollection());
        $themeConfig->setName('Simple Theme');
        $themeConfig->setIsTheme(true);

        $themeId = Uuid::randomHex();

        $this->connectionMock->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
                [
                    'id' => $themeId,
                    'dependentId' => Uuid::randomHex(),
                ],
            ],
            null // will throw excepetion to provoke a db exception
        );

        $this->themeServiceMock->method('getThemeDependencyMapping')->willReturn(
            new ThemeSalesChannelCollection(
                [
                    new ThemeSalesChannel(Uuid::randomHex(), Uuid::randomHex()),
                ]
            )
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ThemeAssignmentException::class);
        } else {
            $this->expectException(ThemeException::class);
        }
        $this->expectExceptionMessageMatches('/^Unable to deactivate or uninstall theme/');

        $this->themeLifecycleHandler->handleThemeUninstall(
            $themeConfig,
            $this->context
        );
    }

    public function testSkipThemeCompilationIfContextStateIsSet(): void
    {
        $config = new StorefrontPluginConfiguration('simple-theme');
        $config->setIsTheme(true);

        $context = Context::createDefaultContext();
        $context->addState(AbstractAppLifecycle::STATE_SKIP_THEME_COMPILATION);

        $themeLifecycleServiceMock = $this->createMock(ThemeLifecycleService::class);
        $themeLifecycleServiceMock
            ->expects($this->once())
            ->method('refreshTheme')
            ->with($config, $context);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $themeServiceMock = $this->createMock(ThemeService::class);
        $themeServiceMock->expects($this->never())->method('compileThemeById');
        $themeServiceMock->expects($this->never())->method('compileTheme');

        $this->buildHandler(
            themeLifecycleService: $themeLifecycleServiceMock,
            themeService: $themeServiceMock,
            connection: $connectionMock
        )->handleThemeInstallOrUpdate(
            $config,
            new StorefrontPluginConfigurationCollection([$config]),
            $context,
        );
    }

    public function testRefreshAllActiveThemeImportMaps(): void
    {
        $configurationCollection = new StorefrontPluginConfigurationCollection();

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['sales_channel_id' => Uuid::randomHex(), 'theme_id' => Uuid::randomHex()],
                ['sales_channel_id' => Uuid::randomHex(), 'theme_id' => Uuid::randomHex()],
            ]);

        $themeServiceMock = $this->createMock(ThemeService::class);
        $themeServiceMock
            ->expects($this->exactly(2))
            ->method('refreshThemeImportMap');

        $this->buildHandler(
            themeService: $themeServiceMock,
            connection: $connectionMock
        )->refreshAllActiveThemeImportMaps($this->context, $configurationCollection);
    }

    /**
     * @param (EntityRepository<ThemeCollection>&MockObject)|null $themeRepository
     */
    private function buildHandler(
        ?ThemeLifecycleService $themeLifecycleService = null,
        ?ThemeService $themeService = null,
        ?EntityRepository $themeRepository = null,
        ?StorefrontPluginRegistry $configurationRegistry = null,
        ?Connection $connection = null
    ): ThemeLifecycleHandler {
        return new ThemeLifecycleHandler(
            $themeLifecycleService ?? $this->themeLifecycleServiceMock,
            $themeService ?? $this->themeServiceMock,
            $themeRepository ?? $this->themeRepositoryMock,
            $configurationRegistry ?? $this->configurationRegistryMock,
            $connection ?? $this->connectionMock
        );
    }
}
