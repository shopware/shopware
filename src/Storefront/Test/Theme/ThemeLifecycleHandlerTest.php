<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Test\Theme\fixtures\SimplePluginWithoutCompilation\SimplePluginWithoutCompilation;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;

class ThemeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var MockObject|ThemeService
     */
    private $themeServiceMock;

    /**
     * @var MockObject|StorefrontPluginRegistryInterface
     */
    private $configurationRegistryMock;

    /**
     * @var ThemeLifecycleHandler
     */
    private $themeLifecycleHandler;

    /**
     * @var StorefrontPluginConfigurationFactory
     */
    private $configFactory;

    public function setUp(): void
    {
        $this->themeServiceMock = $this->createMock(ThemeService::class);

        $this->configurationRegistryMock = $this->createMock(StorefrontPluginRegistryInterface::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            $this->getContainer()->get(ThemeLifecycleService::class),
            $this->themeServiceMock,
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get('theme.repository'),
            $this->configurationRegistryMock
        );

        $this->configFactory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);

        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM `theme_sales_channel`');
        $this->assignThemeToDefaultSalesChannel();
    }

    public function testHandleThemeUninstallWillRecompileThemeIfNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePlugin());

        $this->themeServiceMock->expects(static::once())
            ->method('compileTheme')
            ->with(
                Defaults::SALES_CHANNEL,
                static::isType('string'),
                static::isInstanceOf(Context::class),
                static::callback(function (StorefrontPluginConfigurationCollection $configs): bool {
                    // assert uninstalledConfig is not used when compiling the theme
                    return $configs->count() === 1 && $configs->first()->getTechnicalName() === 'Storefront';
                })
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects(static::once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillNotRecompileThemeIfNotNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePluginWithoutCompilation());

        $this->themeServiceMock->expects(static::never())
            ->method('compileTheme');

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->configurationRegistryMock->expects(static::once())
            ->method('getConfigurations')
            ->willReturn($configs);

        $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
    }

    private function assignThemeToDefaultSalesChannel(): void
    {
        $themeRepository = $this->getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();

        $themeId = $themeRepository->searchIds(new Criteria(), $context)->firstId();

        $themeRepository->update([
            [
                'id' => $themeId,
                'salesChannels' => [
                    [
                        'id' => Defaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ], $context);
    }
}
