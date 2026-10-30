<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeCompilerInterface;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeMergedConfigBuilder;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;

/**
 * @internal
 */
#[Package('discovery')]
class ThemeServiceInheritanceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ThemeService $service;

    private ThemeRuntimeConfigService $runtimeService;

    private ThemeRuntimeConfigStorage $storage;

    private ThemeCompilerInterface&MockObject $compiler;

    private StorefrontPluginConfigurationCollection $configurations;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    private Context $context;

    private StaticSystemConfigService $systemConfig;

    private MessageBus&MockObject $messageBus;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $connection = static::getContainer()->get(Connection::class);
        $this->configurations = clone static::getContainer()->get(StorefrontPluginRegistry::class)->getConfigurations();
        $registry = static::createStub(StorefrontPluginRegistry::class);
        $registry->method('getConfigurations')->willReturn($this->configurations);
        $builder = new ThemeMergedConfigBuilder($registry, $this->themeRepository);
        $resolver = static::createStub(ThemeFileResolver::class);
        $resolver->method('resolveScriptFiles')->willReturn(new FileCollection());
        $this->storage = new ThemeRuntimeConfigStorage($connection);
        $this->runtimeService = new ThemeRuntimeConfigService($resolver, $registry, $builder, $this->storage, new NativeClock());
        $this->compiler = $this->createMock(ThemeCompilerInterface::class);
        $this->systemConfig = new StaticSystemConfigService();
        $this->messageBus = $this->createMock(MessageBus::class);
        $this->service = new ThemeService(
            $registry,
            $this->themeRepository,
            static::getContainer()->get('theme_sales_channel.repository'),
            $this->compiler,
            static::createStub(ScssPhpCompiler::class),
            new EventDispatcher(),
            new DatabaseConfigLoader($this->themeRepository, $registry, static::getContainer()->get('media.repository')),
            $connection,
            $this->systemConfig,
            $this->messageBus,
            static::createStub(NotificationService::class),
            $builder,
            $this->runtimeService,
        );
    }

    public function testDependencyMappingIncludesAllDescendantsAndSalesChannelsOnce(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase');
        $parent = $this->createTheme(name: 'InheritanceParent', parentId: $base);
        $child = $this->createTheme(name: 'InheritanceChild', parentId: $parent);
        $grandchild = $this->createTheme(name: 'InheritanceGrandchild', parentId: $child);
        $unrelated = $this->createTheme(name: 'UnrelatedTheme');

        $baseChannel = $this->createSalesChannel($base);
        $parentChannel = $this->createSalesChannel($parent);
        $childChannel = $this->createSalesChannel($child);
        $secondChildChannel = $this->createSalesChannel($child);
        $grandchildChannel = $this->createSalesChannel($grandchild);
        $this->createSalesChannel($unrelated);

        $mappings = [];
        foreach ($this->service->getThemeDependencyMapping($base) as $mapping) {
            $mappings[] = [$mapping->getThemeId(), $mapping->getSalesChannelId()];
        }

        static::assertCount(5, $mappings);
        static::assertEqualsCanonicalizing([
            [$base, $baseChannel],
            [$parent, $parentChannel],
            [$child, $childChannel],
            [$child, $secondChildChannel],
            [$grandchild, $grandchildChannel],
        ], $mappings);
    }

    public function testSavingUnassignedBaseThemeUpdatesDescendantsAfterReset(): void
    {
        $base = $this->createTheme(
            name: 'InheritanceBase',
            values: ['sw-color-brand-primary' => '#ff0000'],
            inheritance: ['@Storefront'],
        );
        $parent = $this->createTheme(
            name: 'InheritanceParent',
            parentId: $base,
            inheritance: ['@Storefront', '@InheritanceBase'],
        );
        $child = $this->createTheme(
            name: 'InheritanceChild',
            parentId: $parent,
            values: ['sw-color-brand-secondary' => '#123456'],
            inheritance: ['@Storefront', '@InheritanceBase', '@InheritanceParent'],
        );
        $parentChannel = $this->createSalesChannel($parent);
        $childChannel = $this->createSalesChannel($child);
        $secondChildChannel = $this->createSalesChannel($child);
        $this->runtimeService->refreshConfigValues($base, $this->context);

        // Administration saves with reset=true, then writes the final configuration.
        $this->service->resetTheme($base, $this->context);
        $resetRuntime = $this->storage->getById($child);
        static::assertNotNull($resetRuntime);
        static::assertSame('#0000ff', $resetRuntime->resolvedConfig['fields']['sw-color-brand-primary']['value']);

        $compiled = [];
        $this->compiler->expects($this->exactly(3))->method('compileTheme')
            ->willReturnCallback(static function (string $salesChannelId, string $themeId, StorefrontPluginConfiguration $config) use (&$compiled): void {
                $themeConfig = $config->getThemeConfig();
                static::assertNotNull($themeConfig);
                static::assertArrayHasKey('fields', $themeConfig);
                static::assertSame('#00ff00', $themeConfig['fields']['sw-color-brand-primary']['value']);
                $compiled[] = [$themeId, $salesChannelId];
            });

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        static::assertEqualsCanonicalizing([
            [$parent, $parentChannel],
            [$child, $childChannel],
            [$child, $secondChildChannel],
        ], $compiled);
        foreach ([$base, $parent, $child] as $themeId) {
            $runtime = $this->storage->getById($themeId);
            static::assertNotNull($runtime);
            static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
        }
        $childRuntime = $this->storage->getById($child);
        static::assertNotNull($childRuntime);
        static::assertSame('#123456', $childRuntime->resolvedConfig['fields']['sw-color-brand-secondary']['value']);
        $childEntity = $this->themeRepository->search(new Criteria([$child]), $this->context)->getEntities()->get($child);
        static::assertNotNull($childEntity);
        static::assertSame(['sw-color-brand-secondary' => ['value' => '#123456']], $childEntity->getConfigValues());
    }

    public function testSavingUnassignedThemeRefreshesItsOwnRuntime(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase', values: ['sw-color-brand-primary' => '#ff0000']);
        $this->runtimeService->refreshConfigValues($base, $this->context);
        $this->service->resetTheme($base, $this->context);
        $this->compiler->expects($this->never())->method('compileTheme');

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        $runtime = $this->storage->getById($base);
        static::assertNotNull($runtime);
        static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
    }

    public function testAsyncSaveRefreshesRuntimeBeforeDescendantCompilation(): void
    {
        $base = $this->createTheme(name: 'InheritanceBase', inheritance: ['@Storefront']);
        $parent = $this->createTheme(name: 'InheritanceParent', parentId: $base, inheritance: ['@Storefront', '@InheritanceBase']);
        $child = $this->createTheme(name: 'InheritanceChild', parentId: $parent, inheritance: ['@Storefront', '@InheritanceBase', '@InheritanceParent']);
        $parentChannel = $this->createSalesChannel($parent);
        $childChannel = $this->createSalesChannel($child);
        $this->runtimeService->refreshConfigValues($base, $this->context);
        $this->service->resetTheme($base, $this->context);
        $this->systemConfig->set(ThemeService::CONFIG_THEME_COMPILE_ASYNC, true);
        $this->compiler->expects($this->never())->method('compileTheme');
        $queued = [];
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(static function (CompileThemeMessage $message) use (&$queued): Envelope {
                $queued[] = [$message->getThemeId(), $message->getSalesChannelId()];

                return new Envelope($message);
            });

        $this->service->updateTheme($base, ['sw-color-brand-primary' => ['value' => '#00ff00']], null, $this->context);

        static::assertEqualsCanonicalizing([[$parent, $parentChannel], [$child, $childChannel]], $queued);
        foreach ([$base, $parent, $child] as $id) {
            $runtime = $this->storage->getById($id);
            static::assertNotNull($runtime);
            static::assertSame('#00ff00', $runtime->resolvedConfig['fields']['sw-color-brand-primary']['value']);
        }
    }

    /**
     * @param array<string, string> $values
     * @param list<string> $inheritance
     */
    private function createTheme(string $name, ?string $parentId = null, array $values = [], array $inheritance = []): string
    {
        $id = Uuid::randomHex();
        $config = ['fields' => [], 'configInheritance' => $inheritance];
        if ($parentId === null) {
            $config['fields'] = [
                'sw-color-brand-primary' => ['type' => 'color', 'value' => '#0000ff'],
                'sw-color-brand-secondary' => ['type' => 'color', 'value' => '#111111'],
            ];
        }
        $configuration = new StorefrontPluginConfiguration($name);
        $configuration->setThemeConfig($config);
        $this->configurations->add($configuration);
        $this->themeRepository->create([[
            'id' => $id,
            'name' => $name,
            'technicalName' => $name,
            'author' => 'Shopware',
            'active' => true,
            'parentThemeId' => $parentId,
            'baseConfig' => $config,
            'configValues' => array_map(static fn (string $value): array => ['value' => $value], $values),
        ]], $this->context);

        return $id;
    }

    private function createSalesChannel(string $themeId): string
    {
        $repository = static::getContainer()->get('sales_channel.repository');
        $template = $repository->search(new Criteria([TestDefaults::SALES_CHANNEL]), $this->context)->getEntities()->first();
        static::assertInstanceOf(SalesChannelEntity::class, $template);
        $id = Uuid::randomHex();
        $repository->create([[
            'id' => $id,
            'name' => 'Theme inheritance regression',
            'accessKey' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethodId' => $template->getPaymentMethodId(),
            'shippingMethodId' => $template->getShippingMethodId(),
            'navigationCategoryId' => $template->getNavigationCategoryId(),
            'countryId' => $template->getCountryId(),
            'customerGroupId' => $template->getCustomerGroupId(),
        ]], $this->context);
        static::getContainer()->get('theme_sales_channel.repository')->upsert([[
            'themeId' => $themeId,
            'salesChannelId' => $id,
        ]], $this->context);

        return $id;
    }
}
