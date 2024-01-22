<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PluginExtension;

use function PHPUnit\Framework\assertInstanceOf;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\Extension\PluginExtension
 */
class PluginExtensionTest extends TestCase
{
    public function testCreateInstance(): void
    {
        $extension = new PluginExtension([]);
        self:
        assertInstanceOf(\Twig\Extension\AbstractExtension::class, $extension);
    }

    public function testGetFunctions(): void
    {
        $extension = new PluginExtension([]);
        $names = [];
        foreach ($extension->getFunctions() as $function) {
            $names[] = $function->getName();
        }
        static::assertSame(['get_active_plugins'], $names);
    }

    public function getTests(): void
    {
        $extension = new PluginExtension([]);
        $names = [];
        foreach ($extension->getTests() as $key => $test) {
            $names[$key] = $test->getName();
        }
        static::assertSame(['plugin_active' => 'plugin_active'], $names);
    }

    /**
     * @dataProvider getActivePluginsProvider
     */
    public function testGetActivePlugins(array $activePlugins, array $expected): void
    {
        $extension = new PluginExtension($activePlugins);

        static::assertSame($expected, $extension->getActivePlugins());
    }

    public static function getActivePluginsProvider(): iterable
    {
        yield 'one plugin' => [
            'activePlugins' => [
                "Shopware\Commercial\SwagCommercial" => [
                    'name' => 'SwagCommercial',
                    'path' => '/var/www/vendor/store.shopware.com/swagcommercial//src',
                    'class' => "Shopware\Commercial\SwagCommercial",
                ],
            ],
            'expected' => [
                'SwagCommercial',
            ],
        ];

        yield 'no plugins' => [
            'activePlugins' => [],
            'expected' => [],
        ];
    }

    /**
     * @dataProvider pluginActiveProvider
     */
    public function testPluginActive(array $activePlugins, string $name, bool $expected): void
    {
        $extension = new PluginExtension($activePlugins);

        static::assertSame($expected, $extension->isPluginActive($name));
    }

    public static function pluginActiveProvider(): iterable
    {
        yield 'plugin is active' => [
            'activePlugins' => [
                "Shopware\Commercial\SwagCommercial" => [
                    'name' => 'SwagCommercial',
                    'path' => '/var/www/vendor/store.shopware.com/swagcommercial//src',
                    'class' => "Shopware\Commercial\SwagCommercial",
                ],
            ],
            'name' => 'SwagCommercial',
            'expected' => true,
        ];

        yield 'plugin is not active' => [
            'activePlugins' => [
                "Shopware\Commercial\SwagCommercial" => [
                    'name' => 'SwagCommercial',
                    'path' => '/var/www/vendor/store.shopware.com/swagcommercial//src',
                    'class' => "Shopware\Commercial\SwagCommercial",
                ],
            ],
            'name' => 'fooBar',
            'expected' => false,
        ];
    }
}
