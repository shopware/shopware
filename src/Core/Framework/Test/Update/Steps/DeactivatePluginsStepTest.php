<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Steps;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Steps\DeactivatePluginsStep;
use Shopware\Core\Framework\Update\Steps\ValidResult;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;

class DeactivatePluginsStepTest extends TestCase
{
    private const PLUGIN_IDS = [
        '989556e264d24e4fb718b06a216df44f',
        'ee5d01152f3f4a2db2ee42404c93359e',
        'e91e0ce51512495e832ed0163400a382',
        '95c00307aaa4488c9d6317dda8fc4b74',
        '120dc3b191ef4a2d9b9f70922c67aaf3',
    ];

    /**
     * @dataProvider deactivatePluginThrowsExceptionProvider
     * @dataProvider pluginsToDeactivateProvider
     */
    public function testRun(array $constructorArguments, ?\Throwable $expectedException = null): void
    {
        $deactivatePluginsStep = new DeactivatePluginsStep(...$constructorArguments);

        if ($expectedException instanceof \Throwable) {
            static::expectException(\get_class($expectedException));
        }

        $result = $deactivatePluginsStep->run(0);

        static::assertInstanceOf(ValidResult::class, $result);
    }

    /**
     * Returns one test dataset with mocks to check whether all provided plugins are deactivated.
     */
    public function pluginsToDeactivateProvider(): iterable
    {
        $args = $this->getDeactivatePluginsStepConstructorArguments();

        $pluginCompatibilityMock = $this->createConfiguredMock(PluginCompatibility::class, $this->getPluginsToDeactivateArrayConfiguration(self::PLUGIN_IDS));
        $pluginLifecycleServiceMock = $this->createMock(PluginLifecycleService::class);

        $pluginLifecycleServiceMock
            ->expects(static::exactly(\count(self::PLUGIN_IDS)))
            ->method('deactivatePlugin')
            ->withConsecutive(...array_map(static function (string $id): array {
                return [
                    static::callback(static function (PluginEntity $plugin) use ($id): bool {
                        return $plugin->getId() === $id;
                    }),
                    static::isInstanceOf(Context::class),
                ];
            }, self::PLUGIN_IDS));

        $args['pluginLifecycleService'] = $pluginLifecycleServiceMock;
        $args['pluginCompatibility'] = $pluginCompatibilityMock;

        yield [
            'constructorArguments' => array_values($args),
        ];
    }

    /**
     * Returns multiple datasets which cause the deactivatePlugin method to throw various exceptions.
     * Sometimes special exception types are needed to display user-friendly error messages, so this
     * ensures that they're not wrapped/catched.
     */
    public function deactivatePluginThrowsExceptionProvider(): iterable
    {
        $args = $this->getDeactivatePluginsStepConstructorArguments();
        $exceptions = [
            new \Exception('foo'),
            null,
            new ThemeAssignmentException('foo', [], []),
        ];

        $args['pluginCompatibility'] = $this->createConfiguredMock(PluginCompatibility::class, $this->getPluginsToDeactivateDefaultConfiguration());

        foreach ($exceptions as $exception) {
            $pluginLifecycleServiceMock = $this->createMock(PluginLifecycleService::class);

            if ($exception instanceof \Throwable) {
                $pluginLifecycleServiceMock
                    ->method('deactivatePlugin')
                    ->willThrowException($exception);
            }

            $args['pluginLifecycleService'] = $pluginLifecycleServiceMock;

            yield [
                'constructorArguments' => array_values($args),
                'expectedException' => $exception,
            ];
        }
    }

    protected function getDeactivatePluginsStepConstructorArguments(): array
    {
        return [
            'version' => $this->getVersion(),
            'deactivationFilter' => $this->getDeactivationFilter(),
            'pluginCompatibility' => $this->createConfiguredMock(PluginCompatibility::class, []),
            'pluginLifecycleService' => $this->createConfiguredMock(PluginLifecycleService::class, []),
            'systemConfigService' => $this->createConfiguredMock(SystemConfigService::class, []),
            'context' => $this->getContext(),
        ];
    }

    protected function getVersion(?array $override = null): Version
    {
        return $override ? (new Version())->assign($override) : new Version();
    }

    protected function getDeactivationFilter(?string $override = null): string
    {
        return $override ?? PluginCompatibility::PLUGIN_DEACTIVATION_FILTER_ALL;
    }

    protected function getContext(?array $override = null): Context
    {
        return $override ? (Context::createDefaultContext())->assign($override) : Context::createDefaultContext();
    }

    protected function getPluginsToDeactivateArrayConfiguration(array $ids): array
    {
        $plugins = array_map(static function (string $id): PluginEntity {
            $plugin = new PluginEntity();
            $plugin->setId($id);

            return $plugin;
        }, $ids);

        return [
            'getPluginsToDeactivate' => $plugins,
        ];
    }

    protected function getPluginsToDeactivateDefaultConfiguration(): array
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());

        return [
            'getPluginsToDeactivate' => [$plugin],
        ];
    }
}
