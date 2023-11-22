<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeSalesChannel;
use Shopware\Storefront\Theme\ThemeSalesChannelCollection;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[CoversClass(ThemeLifecycleHandler::class)]
class ThemeLifecycleHandlerTest extends TestCase
{
    private MockObject&ThemeService $themeServiceMock;

    private StorefrontPluginRegistryInterface&MockObject $configurationRegistryMock;

    private ThemeLifecycleService&MockObject $themeLifecycleServiceMock;

    private EntityRepository&MockObject $themeRepositoryMock;

    private Connection&MockObject $connectionMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private Context $context;

    protected function setUp(): void
    {
        $this->themeServiceMock = $this->createMock(ThemeService::class);
        $this->configurationRegistryMock = $this->createMock(StorefrontPluginRegistryInterface::class);
        $this->themeLifecycleServiceMock = $this->createMock(ThemeLifecycleService::class);
        $this->themeRepositoryMock = $this->createMock(EntityRepository::class);
        $this->connectionMock = $this->createMock(Connection::class);

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

        $this->configurationRegistryMock->expects(static::once())->method('getConfigurations')->willReturn(
            $collection
        );

        $this->themeRepositoryMock->expects(static::never())->method('upsert');

        $this->themeLifecycleHandler->handleThemeUninstall(
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

        $this->configurationRegistryMock->expects(static::once())->method('getConfigurations')->willReturn(
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

        $this->themeRepositoryMock->expects(static::once())->method('upsert');

        $this->themeLifecycleHandler->handleThemeUninstall(
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

        $this->expectException(ThemeAssignmentException::class);

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

        $this->expectException(ThemeAssignmentException::class);

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
        $context->addState('skip-theme-compilation');

        $this->themeLifecycleServiceMock
            ->expects(static::once())
            ->method('refreshTheme')
            ->with($config, $context);

        $this->connectionMock
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->themeServiceMock->expects(static::never())->method('compileThemeById');
        $this->themeServiceMock->expects(static::never())->method('compileTheme');

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            new StorefrontPluginConfigurationCollection([$config]),
            $context,
        );
    }
}
