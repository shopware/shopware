<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\FeatureFlag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[CoversClass(Feature::class)]
#[CoversClass(FeatureFlagExtension::class)]
class FeatureTest extends TestCase
{
    use EnvTestBehaviour;

    public static string $customCacheId = 'beef3f0ee9c61829627676afd6294bb029';

    /**
     * @var array<string, FeatureFlagConfig>
     */
    private array $registeredFeaturesBackup;

    /**
     * @var list<string>
     */
    private array $fixtureFlags = [
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
    ];

    protected function setUp(): void
    {
        $this->registeredFeaturesBackup = Feature::getRegisteredFeatures();

        $this->setEnvVars([
            'APP_ENV' => 'test',
            'FEATURE_ALL' => 'false',
            'TWIG_COMPILE_TIME_OPTIMIZATION' => 'false',
        ]);

        $this->unsetFixtureEnv();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->registeredFeaturesBackup);
    }

    protected function tearDown(): void
    {
        $this->unsetFixtureEnv();

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->registeredFeaturesBackup);
    }

    public function testABoolGetsReturned(): void
    {
        $this->setUpFixtures();
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));
        $this->setEnvVars(['FEATURE_NEXT_102' => '1']);
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));
    }

    public function testHasFunction(): void
    {
        $this->setUpFixtures();

        static::assertFalse(Feature::has('not-existing'));
        static::assertTrue(Feature::has('FEATURE_NEXT_102'));
    }

    public function testMajorNaming(): void
    {
        $this->setUpFixtures();

        Feature::registerFeature('v6.1.0.0', ['default' => true, 'major' => true]);

        static::assertTrue(Feature::has('v6.1.0.0'));
        static::assertTrue(Feature::has('V6.1.0.0'));
        static::assertTrue(Feature::has('v6_1_0_0'));
        static::assertTrue(Feature::isActive('v6.1.0.0'));
        static::assertTrue(Feature::isActive('v6.1.0.0'));

        Feature::registerFeature('paypal:v1.0.0.0', ['default' => true, 'major' => true]);

        static::assertTrue(Feature::has('paypal:v1.0.0.0'));
        static::assertTrue(Feature::has('PAYPAL:V1.0.0.0'));
        static::assertTrue(Feature::has('paypal_v1_0_0_0'));
    }

    public function testTheCallableGetsExecutes(): void
    {
        $this->setUpFixtures();
        $this->setEnvVars(['FEATURE_NEXT_101' => '0']);
        $indicator = false;
        Feature::ifActive('FEATURE_NEXT_101', static function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertFalse($indicator);

        $this->setEnvVars(['FEATURE_NEXT_101' => '1']);

        Feature::ifActive('FEATURE_NEXT_101', static function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertTrue($indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState(): void
    {
        $currentConfig = array_keys(Feature::getAll(false));
        $featureFlags = array_keys($this->registeredFeaturesBackup);

        static::assertSame(\array_map(Feature::normalizeName(...), $featureFlags), \array_map(Feature::normalizeName(...), $currentConfig));

        $this->setUpFixtures();
        $featureFlags = array_merge($featureFlags, $this->fixtureFlags);

        $configAfterRegistration = array_keys(Feature::getAll(false));
        static::assertSame(\array_map(Feature::normalizeName(...), $featureFlags), \array_map(Feature::normalizeName(...), $configAfterRegistration));
    }

    public function testTwigFeatureFlag(): void
    {
        $this->setUpFixtures();
        $this->registerTwigOptimizationFlag();
        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest.html.twig'), 'featuretest.html.twig');
        $this->setEnvVars(['FEATURE_NEXT_101' => '1']);
        static::assertSame('FeatureIsActive', $template->render([]));
        $this->setEnvVars(['FEATURE_NEXT_101' => '0']);
        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegistered(): void
    {
        $this->registerTwigOptimizationFlag();
        $this->setEnvVars(['APP_ENV' => 'test']);

        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegisteredInProd(): void
    {
        $this->registerTwigOptimizationFlag();
        $this->setEnvVars(['APP_ENV' => 'prod']);

        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testRegisterFeaturesDoesNotOverrideMetaData(): void
    {
        $features = [
            'FEATURE_NEXT_101' => [
                'major' => true,
                'default' => true,
                'description' => 'test',
            ],
        ];
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($features);

        $registeredFeatures = [...array_keys(Feature::getAll(false)), ...['FEATURE_NEXT_102']];
        Feature::registerFeatures($registeredFeatures);

        $actualFeatures = Feature::getRegisteredFeatures();
        static::assertSame($features['FEATURE_NEXT_101'], $actualFeatures['FEATURE_NEXT_101']);

        $expectedFeatureFlags = [
            'FEATURE_NEXT_101' => true,
            'FEATURE_NEXT_102' => false,
        ];
        static::assertSame($expectedFeatureFlags, Feature::getAll(false));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function featureAllDataProvider(): iterable
    {
        yield 'feature all dev true' => ['dev', true];
        yield 'feature all dev false' => ['dev', false];
        yield 'feature all test true' => ['test', true];
        yield 'feature all test false' => ['test', false];
        yield 'feature all prod true' => ['prod', true];
        yield 'feature all prod false' => ['prod', false];
    }

    #[DataProvider('featureAllDataProvider')]
    public function testFeatureAll(string $appEnv, bool $active): void
    {
        $this->setEnvVars([
            'APP_ENV' => $appEnv,
            'FEATURE_ALL' => $active,
            'FEATURE_NEXT_102' => 'true',
        ]);

        $this->setUpFixtures();
        static::assertSame($active, Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));
    }

    /**
     * @return \Generator<string, array{
     *     list<string>|array<string, array<string, bool>>,
     *     array<string, string>,
     *     string,
     *     bool
     * }>
     */
    public static function isActiveDataProvider(): \Generator
    {
        yield 'registered active feature' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_NEXT_101' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered inactive feature' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_NEXT_101' => '',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered inactive feature without env' => [
            [
                'FEATURE_NEXT_101',
            ],
            [],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered inactive feature' => [
            [],
            [
                'FEATURE_NEXT_101' => 'false',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered inactive feature without env' => [
            [],
            [],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered active feature' => [
            [],
            [
                'FEATURE_NEXT_101' => 'true',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered major active feature' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [
                'FEATURE_NEXT_101' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered major inactive feature' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [
                'FEATURE_NEXT_101' => '',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered major inactive feature without env' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered active feature with default false' => [
            [
                'FEATURE_NEXT_101' => [
                    'default' => false,
                ],
            ],
            [
                'FEATURE_NEXT_101' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered inactive feature with default true' => [
            [
                'FEATURE_NEXT_101' => [
                    'default' => true,
                ],
            ],
            [
                'FEATURE_NEXT_101' => '',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered inactive feature without env with default true' => [
            [
                'FEATURE_NEXT_101' => [
                    'default' => true,
                ],
            ],
            [],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered inactive feature without env with default false' => [
            [
                'FEATURE_NEXT_101' => [
                    'default' => false,
                ],
            ],
            [],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered inactive with empty FEATURE_ALL' => [
            [],
            [
                'FEATURE_NEXT_101' => 'false',
                'FEATURE_ALL' => '',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered inactive only empty FEATURE_ALL as env' => [
            [],
            [
                'FEATURE_ALL' => '',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered active with empty FEATURE_ALL' => [
            [],
            [
                'FEATURE_NEXT_101' => 'true',
                'FEATURE_ALL' => '',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'unregistered inactive with minor FEATURE_ALL' => [
            // featureConfig
            [
                'FEATURE_NEXT_102',
            ],
            // env
            [
                'FEATURE_NEXT_101' => 'false',
                'FEATURE_ALL' => '1',
            ],
            // feature to check
            'FEATURE_NEXT_101',
            // expected
            false,
        ];

        yield 'unregistered inactive only minor FEATURE_ALL as env' => [
            [
                'FEATURE_NEXT_102',
            ],
            [
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'unregistered active with minor FEATURE_ALL' => [
            [
                'FEATURE_NEXT_102',
            ],
            [
                'FEATURE_NEXT_101' => 'true',
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered active with minor FEATURE_ALL' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_NEXT_101' => '1',
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered inactive with minor FEATURE_ALL' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered major inactive only with minor FEATURE_ALL env' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered active minor with major FEATURE_ALL' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_NEXT_101' => '1',
                'FEATURE_ALL' => 'major',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered inactive major with major FEATURE_ALL' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_NEXT_101' => '',
                'FEATURE_ALL' => 'major',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered major inactive only with major FEATURE_ALL env' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [
                'FEATURE_ALL' => 'major',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'unregistered inactive only with major FEATURE_ALL env' => [
            [
                'FEATURE_NEXT_102',
            ],
            [
                'FEATURE_ALL' => 'major',
            ],
            'FEATURE_NEXT_101',
            false,
        ];

        yield 'registered inactive with FEATURE_ALL=minor' => [
            [
                'FEATURE_NEXT_101',
            ],
            [
                'FEATURE_ALL' => 'minor',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered major inactive with FEATURE_ALL=minor' => [
            [
                'FEATURE_NEXT_101' => [
                    'major' => true,
                ],
            ],
            [
                'FEATURE_NEXT_101' => '',
                'FEATURE_ALL' => 'minor',
            ],
            'FEATURE_NEXT_101',
            false,
        ];
    }

    /**
     * @param array<string, FeatureFlagConfig>|list<string> $featureConfig
     * @param array<string, string> $env
     */
    #[DataProvider('isActiveDataProvider')]
    public function testIsActive(array $featureConfig, array $env, string $feature, bool $expected): void
    {
        $this->setEnvVars(['APP_ENV' => 'prod', ...$env]);

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($featureConfig);

        static::assertSame(Feature::isActive($feature), $expected);
    }

    private function unsetFixtureEnv(): void
    {
        foreach ($this->fixtureFlags as $flag) {
            unset($_SERVER[$flag], $_ENV[$flag]);
            putenv($flag);
        }
    }

    private function registerTwigOptimizationFlag(): void
    {
        if (Feature::has('TWIG_COMPILE_TIME_OPTIMIZATION')) {
            return;
        }

        Feature::registerFeature('TWIG_COMPILE_TIME_OPTIMIZATION', ['default' => false]);
    }

    private function setUpFixtures(): void
    {
        // init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll(false));
        $registeredFlags = array_merge($registeredFlags, $this->fixtureFlags);

        Feature::registerFeatures($registeredFlags);
    }
}
