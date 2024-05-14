<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Storefront;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeSalesChannel;
use Shopware\Storefront\Theme\ThemeSalesChannelCollection;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles\PluginWithAdditionalBundles;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimplePluginWithoutCompilation\SimplePluginWithoutCompilation;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\SimpleTheme\SimpleTheme;

/**
 * @internal
 */
#[CoversClass(ThemeLifecycleHandler::class)]
class ThemeLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MockObject&ThemeService $themeServiceMock;

    private MockObject&StorefrontPluginRegistryInterface $configurationRegistryMock;

    private ThemeLifecycleHandler $themeLifecycleHandler;

    private StorefrontPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->themeServiceMock = $this->createMock(ThemeService::class);

        $this->configurationRegistryMock = $this->createMock(StorefrontPluginRegistryInterface::class);

        $this->themeLifecycleHandler = new ThemeLifecycleHandler(
            $this->getContainer()->get(ThemeLifecycleService::class),
            $this->themeServiceMock,
            $this->getContainer()->get('theme.repository'),
            $this->configurationRegistryMock,
            $this->getContainer()->get(Connection::class)
        );

        $this->configFactory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM `theme_sales_channel`');
        $this->assignThemeToDefaultSalesChannel();
    }

    public function testHandleThemeInstallOrUpdateWillRecompileThemeIfNecessary(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects(static::once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isType('string'),
                static::isInstanceOf(Context::class),
                static::callback(fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWillRecompilePluginWithSubBundles(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new PluginWithAdditionalBundles(true, __DIR__ . '/fixtures/PluginWithSubBundles'));

        $this->themeServiceMock->expects(static::once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isType('string'),
                static::isInstanceOf(Context::class),
                static::callback(fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeInstallOrUpdateWithInheritance(): void
    {
        $installConfig = $this->configFactory->createFromBundle(new InheritanceWithConfig());

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());

        /** @var EntityRepository $themeRepository */
        $themeRepository = $this->getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'ThemeWithMultiInheritance'));
        $criteria->addAssociation('parentThemes');
        /** @var ThemeEntity $theme */
        $theme = $themeRepository->search($criteria, $context)->first();
    }

    public function testHandleThemeInstallOrUpdateWillRecompileOnlyTouchedTheme(): void
    {
        $salesChannelId = $this->createSalesChannel();
        $themeId = $this->createTheme('SimpleTheme', $salesChannelId);
        $installConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $installConfig->setStyleFiles(FileCollection::createFromArray(['onlyForFile']));

        $this->themeServiceMock->expects(static::once())
            ->method('compileThemeById')
            ->with(
                $themeId,
                static::isInstanceOf(Context::class),
                static::callback(fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 2)
            );

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $installConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($installConfig, $configs, Context::createDefaultContext());
    }

    public function testHandleThemeUninstallWillRecompileThemeIfNecessary(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin'));

        $this->themeServiceMock->expects(static::once())
            ->method('compileTheme')
            ->with(
                TestDefaults::SALES_CHANNEL,
                static::isType('string'),
                static::isInstanceOf(Context::class),
                static::callback(fn (StorefrontPluginConfigurationCollection $configs): bool => $configs->count() === 1 && (
                    (
                        $configs->first() instanceof StorefrontPluginConfiguration
                        ? $configs->first()->getTechnicalName()
                        : ''
                    ) === 'Storefront'
                ))
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

    public function testHandleThemeUninstallWillThrowExceptionIfThemeIsStillInUse(): void
    {
        $uninstalledConfig = $this->configFactory->createFromBundle(new SimpleTheme());
        $uninstalledConfig->setStyleFiles(new FileCollection());
        $uninstalledConfig->setScriptFiles(new FileCollection());

        $configs = new StorefrontPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Storefront()),
            $uninstalledConfig,
        ]);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate($uninstalledConfig, $configs, Context::createDefaultContext());
        $this->assignThemeToDefaultSalesChannel('SimpleTheme');

        $wasThrown = false;

        $scCollection = new ThemeSalesChannelCollection();
        $scCollection->add(new ThemeSalesChannel(Uuid::randomHex(), Uuid::randomHex()));
        $this->themeServiceMock->expects(static::once())
            ->method('getThemeDependencyMapping')
            ->willReturn($scCollection);

        try {
            $this->themeLifecycleHandler->handleThemeUninstall($uninstalledConfig, Context::createDefaultContext());
        } catch (ThemeAssignmentException $e) {
            static::assertEquals(
                [TestDefaults::SALES_CHANNEL],
                array_keys($e->getAssignedSalesChannels() ?? [])
            );
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
    }

    private function assignThemeToDefaultSalesChannel(?string $themeName = null): void
    {
        $themeRepository = $this->getContainer()->get('theme.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        if ($themeName) {
            $criteria->addFilter(new EqualsFilter('technicalName', $themeName));
        }

        $themeId = $themeRepository->searchIds($criteria, $context)->firstId();

        $themeRepository->update([
            [
                'id' => $themeId,
                'salesChannels' => [
                    [
                        'id' => TestDefaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ], $context);
    }

    private function createTheme(string $name, string $salesChannelId): string
    {
        $id = Uuid::randomHex();

        $repository = $this->getContainer()->get('theme.repository');

        $repository->create([
            [
                'id' => $id,
                'technicalName' => $name,
                'name' => $name,
                'author' => 'test',
                'active' => true,
                'salesChannels' => [
                    [
                        'id' => $salesChannelId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $id;
    }

    private function createSalesChannel(): string
    {
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $id = Uuid::randomHex();
        $payload = [[
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'active' => true,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]];

        $salesChannelRepository->create($payload, Context::createDefaultContext());

        return $id;
    }
}
