<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\PluginSchemaPathCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;

/**
 * @covers \Shopware\Core\Framework\Api\ApiDefinition\Generator\PluginSchemaPathCollection
 *
 * @internal
 */
class PluginSchemaPathCollectionTest extends TestCase
{
    private Plugin $activePlugin;

    private Plugin $inactivePlugin;

    public function setUp(): void
    {
        $this->activePlugin = $this->createMock(Plugin::class);
        $this->activePlugin->method('isActive')->willReturn(true);
        $this->activePlugin->method('getPath')->willReturn(__DIR__ . '/_fixtures');
        $this->inactivePlugin = $this->createMock(Plugin::class);
        $this->inactivePlugin->method('isActive')->willReturn(false);
        $this->inactivePlugin->expects(static::never())->method('getPath');
    }

    public function testGetPathsForStoreApi(): void
    {
        $activePlugin = $this->createMock(Plugin::class);
        $activePlugin->method('isActive')->willReturn(true);
        $activePlugin->method('getPath')->willReturn(__DIR__ . '/_fixtures');
        $factory = new PluginSchemaPathCollection(new KernelPluginCollection([$activePlugin]));

        $paths = $factory->getSchemaPaths(DefinitionService::STORE_API);
        static::assertSame([__DIR__ . '/_fixtures/Resources/Schema/StoreApi'], $paths);
    }

    public function testGetPathsForAdminApi(): void
    {
        $factory = new PluginSchemaPathCollection(new KernelPluginCollection([$this->activePlugin]));

        $paths = $factory->getSchemaPaths(DefinitionService::API);
        static::assertSame([__DIR__ . '/_fixtures/Resources/Schema/AdminApi'], $paths);
    }

    public function testSkipsInactivePlugins(): void
    {
        $factory = new PluginSchemaPathCollection(new KernelPluginCollection([$this->inactivePlugin]));

        $paths = $factory->getSchemaPaths(DefinitionService::STORE_API);

        static::assertEmpty($paths);
    }
}
