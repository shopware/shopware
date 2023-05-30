<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\FeatureFlag;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @group skip-paratest
 */
class FeatureTest extends TestCase
{
    use KernelTestBehaviour;

    public static string $featureAllValue;

    public static string $appEnvValue;

    public static string $customCacheId = 'beef3f0ee9c61829627676afd6294bb029';

    /**
     * @var string[]
     */
    private array $fixtureFlags = [
        'FEATURE_NEXT_101',
        'FEATURE_NEXT_102',
    ];

    public static function setUpBeforeClass(): void
    {
        self::$featureAllValue = $_SERVER['FEATURE_ALL'] ?? 'false';
        self::$appEnvValue = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'];
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    public static function tearDownAfterClass(): void
    {
        $_SERVER['FEATURE_ALL'] = self::$featureAllValue;
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'];
        $_SERVER['APP_ENV'] = self::$appEnvValue;
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    protected function setUp(): void
    {
        $_SERVER['FEATURE_ALL'] = 'false';
        $_ENV['FEATURE_ALL'] = 'false';
        $_SERVER['APP_ENV'] = 'test';

        unset($_SERVER['FEATURE_NEXT_101'], $_SERVER['FEATURE_NEXT_102']);

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($this->getContainer()->getParameter('shopware.feature.flags'));
    }

    protected function tearDown(): void
    {
        $_SERVER['APP_ENV'] = self::$appEnvValue;
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'];
        $_SERVER['FEATURE_ALL'] = self::$featureAllValue;
        $_ENV['FEATURE_ALL'] = $_SERVER['FEATURE_ALL'];

        unset($_SERVER['FEATURE_NEXT_101'], $_SERVER['FEATURE_NEXT_102']);

        KernelLifecycleManager::bootKernel(true, self::$customCacheId);
    }

    public function testABoolGetsReturned(): void
    {
        $this->setUpFixtures();
        static::assertFalse(Feature::isActive('FEATURE_NEXT_102'));
        $_SERVER['FEATURE_NEXT_102'] = '1';
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
        $_SERVER['FEATURE_NEXT_101'] = '0';
        $indicator = false;
        Feature::ifActive('FEATURE_NEXT_101', static function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertFalse($indicator);

        $_SERVER['FEATURE_NEXT_101'] = '1';

        Feature::ifActive('FEATURE_NEXT_101', static function () use (&$indicator): void {
            $indicator = true;
        });
        static::assertTrue($indicator);
    }

    public function testConfigGetAllReturnsAllAndTracksState(): void
    {
        $this->setUp();
        $currentConfig = array_keys(Feature::getAll(false));
        $featureFlags = array_keys($this->getContainer()->getParameter('shopware.feature.flags'));

        static::assertEquals(\array_map(Feature::normalizeName(...), $featureFlags), \array_map(Feature::normalizeName(...), $currentConfig));

        $this->setUpFixtures();
        $featureFlags = array_merge($featureFlags, $this->fixtureFlags);

        $configAfterRegistration = array_keys(Feature::getAll(false));
        static::assertEquals(\array_map(Feature::normalizeName(...), $featureFlags), \array_map(Feature::normalizeName(...), $configAfterRegistration));
    }

    public function testTwigFeatureFlag(): void
    {
        $this->setUpFixtures();
        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest.html.twig'), 'featuretest.html.twig');
        $_SERVER['FEATURE_NEXT_101'] = '1';
        static::assertSame('FeatureIsActive', $template->render([]));
        $_SERVER['FEATURE_NEXT_101'] = '0';
        static::assertSame('FeatureIsInactive', $template->render([]));
    }

    public function testTwigFeatureFlagNotRegistered(): void
    {
        $_SERVER['APP_ENV'] = 'test';
        $_ENV['APP_ENV'] = 'test';
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);

        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        $this->expectExceptionMessageMatches('/.*RANDOMFLAGTHATISNOTREGISTERDE471112.*/');

        $template->render([]);
    }

    public function testTwigFeatureFlagNotRegisteredInProd(): void
    {
        $_SERVER['APP_ENV'] = 'prod';
        $_ENV['APP_ENV'] = 'prod';
        KernelLifecycleManager::bootKernel(true, self::$customCacheId);

        $loader = new FilesystemLoader(__DIR__ . '/_fixture/');
        $twig = new Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new FeatureFlagExtension());
        $template = $twig->loadTemplate($twig->getTemplateClass('featuretest_unregistered.html.twig'), 'featuretest_unregistered.html.twig');

        static::assertTrue(true, 'No Notice in prod mode');

        $template->render([]);
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

        /** @var array<string, array{name?: string, default?: boolean, major?: boolean, description?: string}> $registeredFeatures */
        $registeredFeatures = [...array_keys(Feature::getAll(false)), ...['FEATURE_NEXT_102']];
        Feature::registerFeatures($registeredFeatures);

        $actualFeatures = Feature::getRegisteredFeatures();
        static::assertEquals($features['FEATURE_NEXT_101'], $actualFeatures['FEATURE_NEXT_101']);

        $expectedFeatureFlags = [
            'FEATURE_NEXT_101' => true,
            'FEATURE_NEXT_102' => false,
        ];
        static::assertEquals($expectedFeatureFlags, Feature::getAll(false));
    }

    /**
     * @return array{0: string, 1: bool}[]
     */
    public static function featureAllDataProvider(): array
    {
        return [
            ['dev', true],
            ['dev', false],
            ['test', true],
            ['test', false],
            ['prod', true],
            ['prod', false],
        ];
    }

    /**
     * @dataProvider featureAllDataProvider
     */
    public function testFeatureAll(string $appEnv, bool $active): void
    {
        $_SERVER['FEATURE_NEXT_102'] = 'true';

        $_SERVER['APP_ENV'] = $appEnv;
        $_ENV['APP_ENV'] = $appEnv;
        $_SERVER['FEATURE_ALL'] = $active;
        $_ENV['FEATURE_ALL'] = $active;

        $this->setUpFixtures();
        static::assertSame($active, Feature::isActive('FEATURE_NEXT_101'));
        static::assertTrue(Feature::isActive('FEATURE_NEXT_102'));
    }

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
            [
                'FEATURE_NEXT_102',
            ],
            [
                'FEATURE_NEXT_101' => 'false',
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
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
                'FEATURE_NEXT_101' => '',
                'FEATURE_ALL' => '1',
            ],
            'FEATURE_NEXT_101',
            true,
        ];

        yield 'registered major inactive  only with minor FEATURE_ALL env' => [
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

        yield 'registered active major with major FEATURE_ALL' => [
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
            true,
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
                'FEATURE_NEXT_101' => '',
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
     * @param array<string, array{name?: string, default?: boolean, major?: boolean, description?: string}> $featureConfig
     * @param array<string, string> $env
     *
     * @dataProvider isActiveDataProvider
     */
    public function testIsActive(array $featureConfig, array $env, string $feature, bool $expected): void
    {
        $_SERVER['APP_ENV'] = 'prod';
        $_ENV['APP_ENV'] = 'prod';

        KernelLifecycleManager::bootKernel(true, self::$customCacheId);

        foreach ($env as $key => $value) {
            $_SERVER[$key] = $value;
        }

        Feature::resetRegisteredFeatures();
        Feature::registerFeatures($featureConfig);

        static::assertSame(Feature::isActive($feature), $expected);
    }

    private function setUpFixtures(): void
    {
        //init FeatureConfig
        $registeredFlags = array_keys(Feature::getAll(false));
        /** @var array<string, array{name?: string, default?: boolean, major?: boolean, description?: string}> $registeredFlags */
        $registeredFlags = array_merge($registeredFlags, $this->fixtureFlags);

        Feature::registerFeatures($registeredFlags);
    }
}
