<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(KernelPluginCollection::class)]
class KernelPluginCollectionTest extends TestCase
{
    public function testAddKeysThePluginByClassAndIgnoresDuplicates(): void
    {
        $plugin = new ExampleBundle(true, __DIR__);
        $duplicate = new ExampleBundle(true, __DIR__);

        $collection = new KernelPluginCollection();
        $collection->add($plugin);
        $collection->add($duplicate);

        static::assertTrue($collection->has(ExampleBundle::class));
        static::assertSame($plugin, $collection->get(ExampleBundle::class));
        static::assertSame([ExampleBundle::class => $plugin], $collection->all());
    }

    public function testAddListAddsAllPlugins(): void
    {
        $plugin = new ExampleBundle(true, __DIR__);

        $collection = new KernelPluginCollection();
        $collection->addList([$plugin]);

        static::assertSame([ExampleBundle::class => $plugin], $collection->all());
    }

    public function testGetReturnsNullForUnknownPlugins(): void
    {
        static::assertNull((new KernelPluginCollection())->get('Unknown\Plugin'));
    }

    public function testGetActivesFiltersInactivePlugins(): void
    {
        static::assertSame([], (new KernelPluginCollection())->getActives());

        $active = new ExampleBundle(true, __DIR__);
        $collection = new KernelPluginCollection([ExampleBundle::class => $active]);

        static::assertSame([ExampleBundle::class => $active], $collection->getActives());

        $inactive = new ExampleBundle(false, __DIR__);
        $inactiveCollection = new KernelPluginCollection([ExampleBundle::class => $inactive]);

        static::assertSame([], $inactiveCollection->getActives());
    }

    public function testFilter(): void
    {
        $plugin = new ExampleBundle(true, __DIR__);
        $collection = new KernelPluginCollection([ExampleBundle::class => $plugin]);

        static::assertSame(
            [ExampleBundle::class => $plugin],
            $collection->filter(static fn (ExampleBundle $candidate) => $candidate->isActive())->all()
        );
    }
}
