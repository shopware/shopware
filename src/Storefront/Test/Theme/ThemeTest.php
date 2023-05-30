<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Test\Theme\fixtures\SimpleTheme\SimpleTheme;
use Shopware\Storefront\Test\Theme\fixtures\SimpleThemeConfigInheritance\SimpleThemeConfigInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeFixtures;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
class ThemeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ThemeService $themeService;

    private Context $context;

    private EntityRepository $themeRepository;

    private string $createdStorefrontTheme = '';

    private EntityRepository $mediaRepository;

    private string $faviconId;

    private string $demostoreLogoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeService = $this->getContainer()->get(ThemeService::class);
        $this->themeRepository = $this->getContainer()->get('theme.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $this->context = Context::createDefaultContext();

        $theme = $this->themeRepository->search(new Criteria(), $this->context)->first();
        if ($theme === null) {
            $this->createdStorefrontTheme = Uuid::randomHex();
            $this->themeRepository->create([
                [
                    'id' => $this->createdStorefrontTheme,
                    'name' => 'Shopware default theme',
                    'technicalName' => 'Storefront',
                    'active' => true,
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

        $criteria = new Criteria();
        $criteria->addFilter(
            new OrFilter(
                [
                    new EqualsFilter('fileName', 'demostore-logo'),
                    new EqualsFilter('fileName', 'favicon'),
                ]
            )
        );
        $medias = $this->mediaRepository->search($criteria, $this->context);

        /** @var MediaEntity $media */
        foreach ($medias as $media) {
            if ($media->getFileName() === 'favicon') {
                $this->faviconId = $media->getId();
            } elseif ($media->getFileName() === 'demostore-logo') {
                $this->demostoreLogoId = $media->getId();
            }
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

        $themeConfigFix = ThemeFixtures::getThemeConfig($this->faviconId, $this->demostoreLogoId);
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

    public function testChildThemeConfigStructuredFields(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme(
            $baseTheme,
            [
                'fields' => [
                    'some-custom' => [
                        'editable' => false,
                        'section' => 'mainSection',
                        'tab' => 'mainTab',
                    ],
                ],
                'sections' => [
                    'mainSection' => [
                        'label' => [
                            'en-GB' => 'main section',
                        ],
                    ],
                ],
                'tabs' => [
                    'mainTab' => [
                        'label' => [
                            'en-GB' => 'main Tab',
                        ],
                    ],
                ],
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $childThemeFields = $this->themeService->getThemeConfigurationStructuredFields($childTheme->getId(), true, $this->context);
        static::assertEquals(
            'Primary colour',
            $childThemeFields['tabs']['default']['blocks']['themeColors']['sections']['default']['fields']['sw-color-brand-primary']['label']
        );
    }

    public function testChildThemeConfigStructuredFieldsInheritance(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme(
            $baseTheme,
            [
                'fields' => [
                    'some-custom' => [
                        'editable' => false,
                    ],
                ],
            ],
            [],
            'SimpleTheme'
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $factory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);

        $simpleThemeConfig = $factory->createFromBundle(new SimpleThemeConfigInheritance());

        $name = $this->createBundleTheme(
            $simpleThemeConfig,
            $childTheme
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $childThemeFields = $this->themeService->getThemeConfigurationStructuredFields($childTheme->getId(), true, $this->context);
        static::assertEquals(
            'Primary colour',
            $childThemeFields['tabs']['default']['blocks']['themeColors']['sections']['default']['fields']['sw-color-brand-primary']['label']
        );
    }

    public function testInheritedThemeConfig(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme(
            $baseTheme,
            [
                'fields' => [
                    'some-custom' => [
                        'editable' => false,
                    ],
                ],
            ]
        );

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
        $themeInheritedConfig = ThemeFixtures::getThemeInheritedConfig($this->faviconId, $this->demostoreLogoId);

        $someCustom = [
            'name' => 'some-custom',
            'label' => null,
            'type' => null,
            'value' => null,
            'editable' => false,
            'block' => null,
            'section' => null,
            'order' => null,
            'sectionOrder' => null,
            'blockOrder' => null,
            'extensions' => [],
            'helpText' => null,
            'custom' => null,
            'tab' => null,
            'tabOrder' => null,
            'scss' => null,
            'fullWidth' => null,
        ];

        $themeInheritedConfig['fields']['some-custom'] = $someCustom;
        $themeInheritedConfig['currentFields']['some-custom'] = ['value' => null, 'isInherited' => false];
        $themeInheritedConfig['baseThemeFields']['some-custom'] = ['value' => null, 'isInherited' => true];

        $themeInheritedConfig['currentFields']['sw-color-brand-primary']['value'] = '#ff00ff';
        $themeInheritedConfig['currentFields']['sw-color-brand-secondary']['value'] = '#3d444d';

        foreach ($themeInheritedConfig['fields'] as $key => $field) {
            if ($field['type'] === 'media') {
                $themeInheritedConfig['fields'][$key]['value'] = $theme['fields'][$key]['value'];
            }
        }

        static::assertEquals($themeInheritedConfig, $theme);
    }

    /**
     * Check if a Theme without fieldconfigs will also be updateable
     */
    public function testInheritedBlankThemeConfig(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createBlankTheme($baseTheme);

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
        $themeInheritedConfig = ThemeFixtures::getThemeInheritedBlankConfig($this->faviconId, $this->demostoreLogoId);

        $themeInheritedConfig['currentFields']['sw-color-brand-primary']['value'] = '#ff00ff';
        $themeInheritedConfig['currentFields']['sw-color-brand-primary']['isInherited'] = false;

        $themeInheritedConfig['baseThemeFields']['sw-color-brand-primary']['value'] = '#0b539b';

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
        $themeInheritedConfig = ThemeFixtures::getThemeInheritedConfig($this->faviconId, $this->demostoreLogoId);

        $themeInheritedConfig['blocks']['newBlock']['label'] = [
            'en-GB' => 'New Block',
            'de-DE' => 'Neuer Block',
        ];

        foreach ($themeInheritedConfig['fields'] as $key => $field) {
            if ($field['type'] === 'media') {
                $themeInheritedConfig['fields'][$key]['value'] = $theme['fields'][$key]['value'];
            }
        }
        $themeInheritedConfig['currentFields']['sw-color-brand-secondary']['value'] = '#3d444d';

        static::assertEquals($themeInheritedConfig, $theme);
    }

    public function testThemeConfigWithMultiSelect(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $this->context)->first();

        $name = $this->createTheme(
            $baseTheme,
            $this->getCustomConfigMultiSelect()
        );

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
                'multi' => [
                    'value' => ['top'],
                ],
            ],
            null,
            $this->context
        );

        $theme = $this->themeService->getThemeConfiguration($childTheme->getId(), false, $this->context);

        static::assertArrayHasKey('multi', $theme['fields']);
        static::assertArrayHasKey('value', $theme['fields']['multi']);
        static::assertEquals(['top'], $theme['fields']['multi']['value']);
    }

    public function testCompileTheme(): void
    {
        static::markTestSkipped('theme compile is not possible cause app.js does not exists');
        $criteria = new Criteria(); /** @phpstan-ignore-line  */
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

        $themeCompiled = $this->themeService->assignTheme($childTheme->getId(), TestDefaults::SALES_CHANNEL, $this->context);

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
                new IsEqual(TestDefaults::SALES_CHANNEL),
                new Callback(static function (string $value) use (&$_expectedTheme): bool {
                    return $value === $_expectedTheme;
                }),
                new Callback(static function (StorefrontPluginConfiguration $value) use (&$_expectedColor): bool {
                    return $value->getThemeConfig()['fields']['sw-color-brand-primary']['value'] === $_expectedColor; /** @phpstan-ignore-line  */
                })
            );

        $kernel = new class($this->getContainer()->get('kernel')) implements KernelInterface {
            private readonly SimpleTheme $simpleTheme;

            public function __construct(private readonly Kernel $kernel)
            {
                $this->simpleTheme = new SimpleTheme();
            }

            public function getBundles(): array
            {
                $bundles = $this->kernel->getBundles();
                $bundles[$this->simpleTheme->getName()] = $this->simpleTheme;

                return $bundles;
            }

            public function getBundle(string $name): BundleInterface
            {
                return $name === $this->simpleTheme->getName() ? $this->simpleTheme : $this->kernel->getBundle($name);
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return $this->kernel->handle(...\func_get_args());
            }

            public function registerBundles(): iterable
            {
                return $this->kernel->registerBundles();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $this->kernel->registerContainerConfiguration(...\func_get_args());
            }

            public function boot(): void
            {
                $this->kernel->boot();
            }

            public function shutdown(): void
            {
                $this->kernel->shutdown();
            }

            public function locateResource(string $name): string
            {
                return $this->kernel->locateResource(...\func_get_args());
            }

            public function getEnvironment(): string
            {
                return $this->kernel->getEnvironment();
            }

            public function isDebug(): bool
            {
                return $this->kernel->isDebug();
            }

            public function getProjectDir(): string
            {
                return $this->kernel->getProjectDir();
            }

            public function getContainer(): ContainerInterface
            {
                return $this->kernel->getContainer();
            }

            public function getStartTime(): float
            {
                return $this->kernel->getStartTime();
            }

            public function getCacheDir(): string
            {
                return $this->kernel->getCacheDir();
            }

            public function getBuildDir(): string
            {
                return $this->kernel->getBuildDir();
            }

            public function getLogDir(): string
            {
                return $this->kernel->getLogDir();
            }

            public function getCharset(): string
            {
                return $this->kernel->getCharset();
            }

            public function __call($name, $arguments) /* @phpstan-ignore-line  */
            {
                return $this->kernel->$name(...\func_get_args()); /* @phpstan-ignore-line  */
            }
        };

        $themeService = new ThemeService(
            new StorefrontPluginRegistry(
                $kernel,
                $this->getContainer()->get(StorefrontPluginConfigurationFactory::class),
                $this->getContainer()->get(ActiveAppsLoader::class)
            ),
            $this->getContainer()->get('theme.repository'),
            $this->getContainer()->get('theme_sales_channel.repository'),
            $themeCompilerMock,
            $this->getContainer()->get('event_dispatcher'),
            new DatabaseConfigLoader(
                $this->getContainer()->get('theme.repository'),
                new StorefrontPluginRegistry(
                    $kernel,
                    $this->getContainer()->get(StorefrontPluginConfigurationFactory::class),
                    $this->getContainer()->get(ActiveAppsLoader::class)
                ),
                $this->getContainer()->get('media.repository'),
            ),
            $this->getContainer()->get(Connection::class)
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
        $themeService->compileTheme(TestDefaults::SALES_CHANNEL, $childTheme->getId(), $this->context);
        $_expectedColor = '#0b539b';
        $_expectedTheme = $baseTheme->getId();
        $themeService->compileTheme(TestDefaults::SALES_CHANNEL, $baseTheme->getId(), $this->context);
    }

    public function testThemeServiceReturnsCorrectConfigAfterEmptyingThemeMedia(): void
    {
        $name = $this->createParentlessSimpleTheme();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search($criteria, $this->context)->first();

        $data = [
            'id' => $theme->getId(),
            'configValues' => [
                'sw-logo-mobile' => [
                    'value' => null,
                ],
            ],
        ];

        $this->themeRepository->update([$data], $this->context);

        /** @var ThemeEntity $updatedTheme */
        $updatedTheme = $this->themeRepository->search(new Criteria([$theme->getId()]), $this->context)->first();
        static::assertNotNull($updatedTheme->getConfigValues());

        $themeServiceReturnedConfig = $this->themeService->getThemeConfiguration($updatedTheme->getId(), false, $this->context);

        static::assertNotNull($themeServiceReturnedConfig['fields']['sw-logo-desktop']['value']);
        static::assertNull($themeServiceReturnedConfig['fields']['sw-logo-mobile']['value']);
    }

    public function testThemeServiceUpdate(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search($criteria, $this->context)->first();

        $theme->setConfigValues(
            [
                'test' => [
                    'value' => true,
                ],
            ]
        );

        $name = $this->createTheme(
            $theme,
            [
                'fields' => [
                    'some-custom' => [
                        'editable' => false,
                    ],
                ],
            ],
            [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        try {
            $this->themeService->updateTheme(
                $childTheme->getId(),
                [
                    'fields' => [
                        'some-custom' => [
                            'editable' => true,
                        ],
                    ],
                    'test' => [
                        'value' => [false],
                    ],
                ],
                $theme->getId(),
                Context::createDefaultContext()
            );
        } catch (ThemeCompileException $e) {
            //ignore files not found exception

            if ($e->getMessage() !== 'Unable to compile the theme "Shopware default theme". Files could not be resolved with error: Unable to compile the theme "Storefront". Unable to load file "src/Storefront/Resources/app/storefront/dist/js/vendor-node.js". Did you forget to build the theme? Try running ./bin/build-storefront.sh') {
                throw $e;
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $childTheme */
        $childTheme = $this->themeRepository->search($criteria, $this->context)->first();

        static::assertEquals(
            [
                'fields' => [
                    'some-custom' => [
                        'editable' => true,
                    ],
                ],
                'test' => [
                    'value' => [false],
                ],
            ],
            $childTheme->getConfigValues()
        );
    }

    public function testThemeServiceUpdateWrongId(): void
    {
        $randomId = Uuid::randomHex();
        static::expectExceptionMessage('Unable to find the theme "' . $randomId . '"');
        $this->themeService->updateTheme($randomId, null, null, Context::createDefaultContext());
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
        $name = $this->createParentlessSimpleTheme();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search($criteria, $this->context)->first();
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
        $resetTheme = $this->themeRepository->search($criteria, $this->context)->first();

        static::assertEmpty($resetTheme->getConfigValues());
        static::assertNotEmpty($resetTheme->getUpdatedAt());
    }

    private function createBundleTheme(StorefrontPluginConfiguration $config, ThemeEntity $parentTheme): string
    {
        $name = $config->getTechnicalName();

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
                    'baseConfig' => array_merge($parentTheme->getBaseConfig() ?? [], $config->getThemeConfig() ?? []),
                    'description' => $parentTheme->getDescription(),
                    'author' => $parentTheme->getAuthor(),
                    'labels' => $parentTheme->getLabels(),
                    'customFields' => $parentTheme->getCustomFields(),
                    'previewMediaId' => $parentTheme->getPreviewMediaId(),
                    'active' => true,
                    'salesChannels' => [],
                ],
            ],
            $this->context
        );

        return $name;
    }

    /**
     * @param array<string, mixed> $customConfig
     * @param array<int, array<string, string>> $saleschannels
     */
    private function createTheme(ThemeEntity $parentTheme, array $customConfig = [], array $saleschannels = [], ?string $givenName = null): string
    {
        $name = $givenName ?? 'test' . Uuid::randomHex();

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
                    'baseConfig' => array_merge_recursive($parentTheme->getBaseConfig() ?? [], $customConfig),
                    'description' => $parentTheme->getDescription(),
                    'author' => $parentTheme->getAuthor(),
                    'labels' => $parentTheme->getLabels(),
                    'customFields' => $parentTheme->getCustomFields(),
                    'previewMediaId' => $parentTheme->getPreviewMediaId(),
                    'active' => true,
                    'salesChannels' => $saleschannels,
                ],
            ],
            $this->context
        );

        return $name;
    }

    private function createBlankTheme(ThemeEntity $parentTheme): string
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
                    'description' => $parentTheme->getDescription(),
                    'author' => $parentTheme->getAuthor(),
                    'labels' => $parentTheme->getLabels(),
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

    /**
     * @return array<string, mixed>
     */
    private function getCustomConfigMultiSelect(): array
    {
        return [
            'fields' => [
                'multi' => [
                    'label' => [
                        'en-GB' => 'Multi',
                        'de-DE' => 'Multi',
                    ],
                    'scss' => false,
                    'type' => 'text',
                    'value' => [
                        0 => 'top',
                        1 => 'bottom',
                    ],
                    'custom' => [
                        'componentName' => 'sw-multi-select',
                        'options' => [
                            0 => [
                                'value' => 'bottom',
                                'label' => [
                                    'en-GB' => 'bottom',
                                    'de-DE' => 'unten',
                                ],
                            ],
                            1 => [
                                'value' => 'top',
                                'label' => [
                                    'en-GB' => 'top',
                                    'de-DE' => 'oben',
                                ],
                            ],
                            2 => [
                                'value' => 'middle',
                                'label' => [
                                    'en-GB' => 'middle',
                                    'de-DE' => 'mittel',
                                ],
                            ],
                        ],
                    ],
                    'editable' => true,
                ],
            ],
        ];
    }
}
