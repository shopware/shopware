<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Feature;

use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Event\BeforeFeatureFlagToggleEvent;
use Shopware\Core\Framework\Feature\Event\FeatureFlagToggledEvent;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @phpstan-import-type FeatureFlagConfig from Feature
 */
#[CoversClass(FeatureFlagRegistry::class)]
class FeatureFlagRegistryTest extends TestCase
{
    public function testDisableWithoutEnabledFeatureToggle(): void
    {
        $exception = FeatureException::featureCannotBeToggled('FEATURE_ABC');
        static::expectExceptionObject($exception);
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'toggleable' => true,
                'major' => false,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            false
        );

        $service->disable('FEATURE_ABC');
    }

    public function testEnableWithoutEnabledFeatureToggle(): void
    {
        $exception = FeatureException::featureCannotBeToggled('FEATURE_ABC');
        static::expectExceptionObject($exception);
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => false,
                'toggleable' => true,
                'major' => false,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            false
        );

        $service->enable('FEATURE_ABC');
    }

    public function testDisableNonExistFeature(): void
    {
        $exception = FeatureException::featureNotRegistered('FEATURE_NON_EXIST');
        static::expectExceptionObject($exception);
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'toggleable' => true,
                'major' => true,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->disable('FEATURE_NON_EXIST');
    }

    public function testDisableToggleableMajorFeature(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'toggleable' => true,
                'major' => true,
            ],
        ]);
        static::assertTrue(Feature::isActive('FEATURE_MAJOR'));

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->disable('FEATURE_MAJOR');
        static::assertFalse(Feature::isActive('FEATURE_MAJOR'));
    }

    public function testDisableToggleableFeature(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_TOGGLEABLE_FALSE' => [
                'active' => true,
                'toggleable' => false,
            ],
            'FEATURE_TOGGLEABLE_TRUE' => [
                'active' => true,
                'toggleable' => true,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->disable('FEATURE_TOGGLEABLE_TRUE');
        static::assertFalse(Feature::isActive('FEATURE_TOGGLEABLE_TRUE'));

        $exception = FeatureException::featureCannotBeToggled('FEATURE_TOGGLEABLE_FALSE');
        static::expectExceptionObject($exception);
        $service->disable('FEATURE_TOGGLEABLE_FALSE');
    }

    public function testEnableToggleableFeature(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_TOGGLEABLE_FALSE' => [
                'active' => false,
                'toggleable' => false,
            ],
            'FEATURE_TOGGLEABLE_TRUE' => [
                'active' => false,
                'toggleable' => true,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->enable('FEATURE_TOGGLEABLE_TRUE');
        static::assertTrue(Feature::isActive('FEATURE_TOGGLEABLE_TRUE'));

        $exception = FeatureException::featureCannotBeToggled('FEATURE_TOGGLEABLE_FALSE');
        static::expectExceptionObject($exception);
        $service->enable('FEATURE_TOGGLEABLE_FALSE');
    }

    public function testEnableNonExistFeature(): void
    {
        $exception = FeatureException::featureNotRegistered('FEATURE_NON_EXIST');
        static::expectExceptionObject($exception);
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'major' => true,
            ],
        ]);

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->enable('FEATURE_NON_EXIST');
    }

    public function testEnableToggleableMajorFeature(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'major' => true,
                'toggleable' => true,
            ],
        ]);
        static::assertFalse(Feature::isActive('FEATURE_MAJOR'));

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->enable('FEATURE_MAJOR');
        static::assertTrue(Feature::isActive('FEATURE_MAJOR'));
    }

    public function testEnableNoneToggleableMajorFeatureThrowsException(): void
    {
        $exception = FeatureException::featureCannotBeToggled('FEATURE_MAJOR');
        static::expectExceptionObject($exception);
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'major' => true,
                'toggleable' => false,
            ],
        ]);
        static::assertFalse(Feature::isActive('FEATURE_MAJOR'));

        $service = new FeatureFlagRegistry(
            new ArrayKeyValueStorage(),
            new EventDispatcher(),
            [],
            true
        );

        $service->enable('FEATURE_MAJOR');
    }

    public function testDisable(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'toggleable' => true,
                'major' => true,
            ],
        ]);

        $storage = new ArrayKeyValueStorage();
        $dispatcher = new EventDispatcher();

        $service = new FeatureFlagRegistry(
            $storage,
            $dispatcher,
            [],
            true
        );

        $service->disable('FEATURE_ABC');

        static::assertTrue($storage->has('feature.flags'));
        static::assertEquals([
            'FEATURE_ABC' => [
                'active' => false,
                'major' => false,
                'default' => false,
                'toggleable' => true,
                'description' => '',
                'static' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'major' => true,
                'default' => false,
                'toggleable' => true,
                'description' => '',
            ],
        ], $storage->get('feature.flags'));
        static::assertFalse(Feature::isActive('FEATURE_ABC'));
    }

    public function testEnable(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => false,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'toggleable' => true,
                'major' => true,
            ],
        ]);

        $storage = new ArrayKeyValueStorage();
        $dispatcher = new EventDispatcher();

        $service = new FeatureFlagRegistry(
            $storage,
            $dispatcher,
            [],
            true
        );

        $service->enable('FEATURE_ABC');

        static::assertTrue($storage->has('feature.flags'));
        static::assertEquals([
            'FEATURE_ABC' => [
                'active' => true,
                'major' => false,
                'default' => false,
                'toggleable' => true,
                'description' => '',
                'static' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => true,
                'major' => true,
                'default' => false,
                'toggleable' => true,
                'description' => '',
            ],
        ], $storage->get('feature.flags'));
        static::assertTrue(Feature::isActive('FEATURE_ABC'));
    }

    public function testEnableDispatchEvent(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => false,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'toggleable' => true,
                'major' => true,
            ],
        ]);

        $storage = new ArrayKeyValueStorage();
        $dispatcher = new EventDispatcher();

        $beforeEventDispatched = false;
        $toggledEventDispatched = false;

        $dispatcher->addListener(BeforeFeatureFlagToggleEvent::class, function (BeforeFeatureFlagToggleEvent $event) use (&$beforeEventDispatched): void {
            static::assertSame('FEATURE_ABC', $event->feature);
            static::assertTrue($event->active);
            $beforeEventDispatched = true;
        });

        $dispatcher->addListener(FeatureFlagToggledEvent::class, function (FeatureFlagToggledEvent $event) use (&$toggledEventDispatched): void {
            static::assertSame('FEATURE_ABC', $event->feature);
            static::assertTrue($event->active);
            $toggledEventDispatched = true;
        });

        $service = new FeatureFlagRegistry(
            $storage,
            $dispatcher,
            [],
            true
        );

        $service->enable('FEATURE_ABC');

        static::assertTrue($beforeEventDispatched);
        static::assertTrue($toggledEventDispatched);
        static::assertTrue($storage->has('feature.flags'));
        static::assertEquals([
            'FEATURE_ABC' => [
                'active' => true,
                'major' => false,
                'default' => false,
                'toggleable' => true,
                'description' => '',
                'static' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'major' => true,
                'default' => false,
                'toggleable' => true,
                'description' => '',
            ],
        ], $storage->get('feature.flags'));
    }

    public function testDisableDispatchEvent(): void
    {
        Feature::resetRegisteredFeatures();
        Feature::registerFeatures([
            'FEATURE_ABC' => [
                'active' => true,
                'toggleable' => true,
                'major' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'toggleable' => true,
                'major' => true,
            ],
        ]);

        $storage = new ArrayKeyValueStorage();
        $dispatcher = new EventDispatcher();

        $beforeEventDispatched = false;
        $toggledEventDispatched = false;

        $dispatcher->addListener(BeforeFeatureFlagToggleEvent::class, function (BeforeFeatureFlagToggleEvent $event) use (&$beforeEventDispatched): void {
            static::assertSame('FEATURE_ABC', $event->feature);
            static::assertFalse($event->active);
            $beforeEventDispatched = true;
        });

        $dispatcher->addListener(FeatureFlagToggledEvent::class, function (FeatureFlagToggledEvent $event) use (&$toggledEventDispatched): void {
            static::assertSame('FEATURE_ABC', $event->feature);
            static::assertFalse($event->active);
            $toggledEventDispatched = true;
        });

        $registry = new FeatureFlagRegistry(
            $storage,
            $dispatcher,
            [],
            true
        );

        $registry->disable('FEATURE_ABC');

        static::assertTrue($beforeEventDispatched);
        static::assertTrue($toggledEventDispatched);
        static::assertTrue($storage->has('feature.flags'));
        static::assertEquals([
            'FEATURE_ABC' => [
                'active' => false,
                'major' => false,
                'default' => false,
                'toggleable' => true,
                'description' => '',
                'static' => false,
            ],
            'FEATURE_MAJOR' => [
                'active' => false,
                'major' => true,
                'default' => false,
                'toggleable' => true,
                'description' => '',
            ],
        ], $storage->get('feature.flags'));
    }

    public function testRegisterWhenNoConnection(): void
    {
        Feature::resetRegisteredFeatures();
        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects(static::once())->method('get')->with(FeatureFlagRegistry::STORAGE_KEY, [])->willThrowException($this->createMock(ConnectionException::class));

        $service = new FeatureFlagRegistry(
            $storage,
            new EventDispatcher(),
            [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            true
        );

        $service->register();

        static::assertEquals([
            'FEATURE_STORED' => [
                'active' => true,
                'major' => false,
                'default' => false,
                'toggleable' => true,
                'description' => '',
            ],
        ], Feature::getRegisteredFeatures());
    }

    /**
     * @param array<string, FeatureFlagConfig> $staticFeatureFlags
     * @param array<string, FeatureFlagConfig>|string $stored
     * @param array<string, FeatureFlagConfig> $expected
     */
    #[DataProvider('registerDataProvider')]
    public function testRegister(bool $enabled, array $staticFeatureFlags, array|string $stored, array $expected): void
    {
        Feature::resetRegisteredFeatures();
        $storage = new ArrayKeyValueStorage(['feature.flags' => $stored]);

        $service = new FeatureFlagRegistry(
            $storage,
            new EventDispatcher(),
            $staticFeatureFlags,
            $enabled
        );

        $service->register();

        static::assertEquals($expected, Feature::getRegisteredFeatures());
    }

    /**
     * @return iterable<array-key, array{staticFeatureFlags: array<string, FeatureFlagConfig>, stored: array<string, FeatureFlagConfig>|string, expected: array<string, FeatureFlagConfig>}>
     */
    public static function registerDataProvider(): iterable
    {
        yield 'register empty' => [
            'enabled' => true,
            'staticFeatureFlags' => [],
            'stored' => [],
            'expected' => [],
        ];

        yield 'register static' => [
            'enabled' => true,
            'staticFeatureFlags' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
            'stored' => [],
            'expected' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
        ];

        yield 'register stored' => [
            'enabled' => true,
            'staticFeatureFlags' => [],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
        ];

        yield 'register stored without enable feature toggle' => [
            'enabled' => false,
            'staticFeatureFlags' => [],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [],
        ];

        yield 'register stored is string' => [
            'enabled' => true,
            'staticFeatureFlags' => [],
            'stored' => \json_encode([
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ], \JSON_THROW_ON_ERROR),
            'expected' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
        ];

        yield 'register static and stored' => [
            'enabled' => true,
            'staticFeatureFlags' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
        ];

        yield 'register static and stored without enabled feature toggle' => [
            'enabled' => false,
            'staticFeatureFlags' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
        ];

        yield 'register static and stored with major' => [
            'enabled' => true,
            'staticFeatureFlags' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => true,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
        ];

        yield 'register static and stored with major and static' => [
            'enabled' => true,
            'staticFeatureFlags' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
            'stored' => [
                'FEATURE_STORED' => [
                    'active' => true,
                    'major' => true,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => true,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                ],
            ],
            'expected' => [
                'FEATURE_STATIC' => [
                    'active' => true,
                    'major' => false,
                    'default' => false,
                    'toggleable' => true,
                    'description' => '',
                    'static' => true,
                ],
            ],
        ];
    }
}
