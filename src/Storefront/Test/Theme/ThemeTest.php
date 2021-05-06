<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Test\Theme\fixtures\ThemeFixtures;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

class ThemeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var ThemeService
     */
    protected $themeService;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var string
     */
    private $createdStorefrontTheme = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeService = $this->getContainer()->get(ThemeService::class);
        $this->themeRepository = $this->getContainer()->get('theme.repository');

        $this->context = Context::createDefaultContext();

        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();
        if ($theme === null) {
            $this->createdStorefrontTheme = Uuid::randomHex();
            $this->themeRepository->create([
                [
                    'id' => $this->createdStorefrontTheme,
                    'name' => 'Storefront',
                    'technicalName' => 'Storefront',
                    'author' => 'Shopware AG',
                    'labels' => [
                        'en-GB' => [
                            'sw-color-brand-primary' => 'Primary colour',
                        ],
                        'de-DE' => [
                            'sw-color-brand-primary' => 'Primärfarbe',
                        ],
                    ],
                ],
            ], $this->context);
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdStorefrontTheme !== '') {
            $this->themeRepository->delete([['id' => $this->createdStorefrontTheme]], $this->context);
        }
    }

    public function testDefaultThemeConfig(): void
    {
        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();
        $themeConfiguration = $this->themeService->getThemeConfiguration($theme->getId(), false, $this->context);

        $themeConfigFix = ThemeFixtures::getThemeConfig();
        foreach ($themeConfigFix['fields'] as $key => $field) {
            if ($field['type'] === 'media') {
                $themeConfigFix['fields'][$key]['value'] = $themeConfiguration['fields'][$key]['value'];
            }
        }
        static::assertEquals($themeConfigFix, $themeConfiguration);
    }

    public function testDefaultThemeConfigTranslated(): void
    {
        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();
        $themeConfiguration = $this->themeService->getThemeConfiguration($theme->getId(), true, $this->context);

        static::assertGreaterThan(0, \count($themeConfiguration));

        foreach ($themeConfiguration['fields'] as $item) {
            static::assertStringNotContainsString('sw-theme', $item['label']);
        }
    }

    public function testDefaultThemeConfigStructuredFields(): void
    {
        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();

        $theme = $this->themeService->getThemeConfigurationStructuredFields($theme->getId(), false, $this->context);
        static::assertEquals(ThemeFixtures::getThemeStructuredFields(), $theme);
    }

    public function testInheritedThemeConfig(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme($baseTheme);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $this->themeService->updateTheme(
            $childTheme->getId(),
            [
                'sw-color-brand-primary' => [
                    'value' => '#ff00ff',
                ],
            ],
            null,
            $this->context
        );

        $theme = $this->themeService->getThemeConfiguration($childTheme->getId(), false, $this->context);
        $themeInheritedConfig = ThemeFixtures::getThemeInheritedConfig();

        foreach ($themeInheritedConfig['fields'] as $key => $field) {
            if ($field['type'] === 'media') {
                $themeInheritedConfig['fields'][$key]['value'] = $theme['fields'][$key]['value'];
            }
        }

        static::assertEquals($themeInheritedConfig, $theme);
    }

    public function testInheritedSecondLevelThemeConfig(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme($baseTheme, [
            'blocks' => [
                'newBlock' => [
                    'label' => [
                        'en-GB' => 'New Block',
                        'de-DE' => 'Neuer Block',
                    ],
                ],
            ],
        ]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $name));

        /** @var ThemeEntity $inheritedTheme */
        $inheritedTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme($inheritedTheme);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $this->themeService->updateTheme(
            $childTheme->getId(),
            [
                'sw-color-brand-primary' => [
                    'value' => '#ff00ff',
                ],
            ],
            null,
            $this->context
        );

        $theme = $this->themeService->getThemeConfiguration($childTheme->getId(), false, $this->context);
        $themeInheritedConfig = ThemeFixtures::getThemeInheritedConfig();

        $themeInheritedConfig['blocks']['newBlock']['label'] = [
            'en-GB' => 'New Block',
            'de-DE' => 'Neuer Block',
        ];

        foreach ($themeInheritedConfig['fields'] as $key => $field) {
            if ($field['type'] === 'media') {
                $themeInheritedConfig['fields'][$key]['value'] = $theme['fields'][$key]['value'];
            }
        }

        static::assertEquals($themeInheritedConfig, $theme);
    }

    public function testCompileTheme(): void
    {
        static::markTestSkipped('theme compile is not possible cause app.js does not exists');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme($baseTheme);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $this->themeService->updateTheme(
            $childTheme->getId(),
            [
                'sw-color-brand-primary' => [
                    'value' => '#ff00ff',
                ],
            ],
            null,
            $this->context
        );

        $themeCompiled = $this->themeService->assignTheme($childTheme->getId(), Defaults::SALES_CHANNEL, $this->context);

        static::assertTrue($themeCompiled);
    }

    public function testCompileNonStorefrontThemesWithSameTechnicalNameNotLeakingConfigurationFromPreviousCompilations(): void
    {
        $this->createParentlessSimpleTheme();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SimpleTheme'));
        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $this->createTheme($baseTheme)));
        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();
        $this->themeRepository->update([[
            'id' => $childTheme->getId(),
            'technicalName' => null,
        ]], $this->context);

        $_expectedColor = '';
        $_expectedTheme = '';
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $themeCompilerMock->expects(static::exactly(2))
            ->method('compileTheme')
            ->with(
                new IsEqual(Defaults::SALES_CHANNEL),
                new Callback(static function (string $value) use (&$_expectedTheme): bool {
                    return $value === $_expectedTheme;
                }),
                new Callback(static function (StorefrontPluginConfiguration $value) use (&$_expectedColor): bool {
                    return $value->getThemeConfig()['fields']['sw-color-brand-primary']['value'] === $_expectedColor;
                })
            );

        $themeService = new ThemeService(
            new StorefrontPluginRegistry(
                new class($this->getContainer()->get('kernel')) implements KernelInterface {
                    /**
                     * @var KernelInterface
                     */
                    private $kernel;

                    /**
                     * @var fixtures\SimpleTheme\SimpleTheme
                     */
                    private $simpleTheme;

                    public function __construct(KernelInterface $kernel)
                    {
                        $this->kernel = $kernel;
                        $this->simpleTheme = new fixtures\SimpleTheme\SimpleTheme();
                    }

                    public function getBundles()
                    {
                        $bundles = $this->kernel->getBundles();
                        $bundles[$this->simpleTheme->getName()] = $this->simpleTheme;

                        return $bundles;
                    }

                    public function getBundle($name)
                    {
                        return $name === $this->simpleTheme->getName() ? $this->simpleTheme : $this->kernel->getBundle($name);
                    }

                    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function registerBundles()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function registerContainerConfiguration(LoaderInterface $loader)
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function boot()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function shutdown()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function locateResource($name)
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getName()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getEnvironment()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function isDebug()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getRootDir()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getProjectDir()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getContainer()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getStartTime()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getCacheDir()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getLogDir()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function getCharset()
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }

                    public function __call($name, $arguments)
                    {
                        return $this->kernel->{__FUNCTION__}(...\func_get_args());
                    }
                },
                $this->getContainer()->get(StorefrontPluginConfigurationFactory::class),
                $this->getContainer()->get(ActiveAppsLoader::class)
            ),
            $this->getContainer()->get('theme.repository'),
            $this->getContainer()->get('theme_sales_channel.repository'),
            $themeCompilerMock,
            $this->getContainer()->get('event_dispatcher'),
        );
        $themeService->updateTheme(
            $childTheme->getId(),
            [
                'sw-color-brand-primary' => [
                    'value' => '#b1900f',
                ],
            ],
            null,
            $this->context
        );

        $_expectedColor = '#b1900f';
        $_expectedTheme = $childTheme->getId();
        $themeService->compileTheme(Defaults::SALES_CHANNEL, $childTheme->getId(), $this->context);
        $_expectedColor = '#008490';
        $_expectedTheme = $baseTheme->getId();
        $themeService->compileTheme(Defaults::SALES_CHANNEL, $baseTheme->getId(), $this->context);
    }

    public function testRefreshPlugin(): void
    {
        $themeLifecycleService = $this->getContainer()->get(ThemeLifecycleService::class);
        $themeLifecycleService->refreshThemes($this->context);
        $themes = $this->themeRepository->search(new Criteria(), $this->context);

        static::assertCount(1, $themes->getElements());
        /** @var ThemeEntity $theme */
        $theme = $themes->first();
        static::assertSame('Storefront', $theme->getTechnicalName());
        static::assertNotEmpty($theme->getLabels());
    }

    public function testResetTheme(): void
    {
        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();
        static::assertEmpty($theme->getConfigValues());

        $data = [
            'id' => $theme->getId(),
            'configValues' => [
                'sw-color-brand-primary' => [
                    'value' => '#ff00ff',
                ],
            ],
        ];

        $this->themeRepository->update([$data], $this->context);

        /** @var ThemeEntity $updatedTheme */
        $updatedTheme = $this->themeRepository->search(new Criteria([$theme->getId()]), $this->context)->first();
        static::assertNotNull($updatedTheme->getConfigValues());

        $this->themeService->resetTheme($theme->getId(), $this->context);

        /** @var ThemeEntity $resetTheme */
        $resetTheme = $this->themeRepository->search(new Criteria(), $this->context)->first();

        static::assertEmpty($resetTheme->getConfigValues());
        static::assertNotEmpty($resetTheme->getUpdatedAt());
    }

    /**
     * @throws \Exception
     */
    private function createTheme(ThemeEntity $parentTheme, array $customConfig = []): string
    {
        $name = 'test' . Uuid::randomHex();

        $id = Uuid::randomHex();
        $this->themeRepository->create(
            [
                [
                    'id' => $id,
                    'parentThemeId' => $parentTheme->getId(),
                    'name' => $name,
                    'technicalName' => $name,
                    'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'configValues' => $parentTheme->getConfigValues(),
                    'baseConfig' => array_merge_recursive($parentTheme->getBaseConfig(), $customConfig),
                    'description' => $parentTheme->getDescription(),
                    'author' => $parentTheme->getAuthor(),
                    'labels' => $parentTheme->getLabels(),
                    'customFields' => $parentTheme->getCustomFields(),
                    'previewMediaId' => $parentTheme->getPreviewMediaId(),
                    'active' => true,
                ],
            ],
            $this->context
        );

        return $name;
    }

    /**
     * @throws \Exception
     */
    private function createParentlessSimpleTheme(): string
    {
        $name = 'test' . Uuid::randomHex();

        $id = Uuid::randomHex();
        $this->themeRepository->create(
            [
                [
                    'id' => $id,
                    'parentThemeId' => null,
                    'name' => $name,
                    'technicalName' => 'SimpleTheme',
                    'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'configValues' => null,
                    'baseConfig' => [],
                    'description' => 'This is a theme',
                    'author' => 'Shopware AG',
                    'labels' => [],
                    'customFields' => [],
                    'previewMediaId' => null,
                    'active' => true,
                ],
            ],
            $this->context
        );

        return $name;
    }
}
