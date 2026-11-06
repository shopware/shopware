<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\AbstractContentSystemLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetRegistry::class)]
class ContentSystemLayoutPresetRegistryTest extends TestCase
{
    #[TestDox('aggregates presets from multiple loaders keyed by id')]
    public function testAllAggregatesFromMultipleLoaders(): void
    {
        $loaderA = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderA->method('load')->willReturn([$this->preset('core.text-block')]);

        $loaderB = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderB->method('load')->willReturn([$this->preset('app.hero')]);

        $registry = new ContentSystemLayoutPresetRegistry([$loaderA, $loaderB]);

        $all = $registry->all();
        static::assertCount(2, $all);
        static::assertArrayHasKey('core.text-block', $all);
        static::assertArrayHasKey('app.hero', $all);
    }

    #[TestDox('returns an empty array when no loaders are registered')]
    public function testAllReturnsEmptyArrayWithNoLoaders(): void
    {
        static::assertSame([], (new ContentSystemLayoutPresetRegistry([]))->all());
    }

    #[TestDox('returns true for a known id and false for an unknown one')]
    public function testHas(): void
    {
        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn([$this->preset('core.text-block')]);

        $registry = new ContentSystemLayoutPresetRegistry([$loader]);

        static::assertTrue($registry->has('core.text-block'));
        static::assertFalse($registry->has('core.unknown'));
    }

    #[TestDox('returns the preset for a known id')]
    public function testGetReturnsPreset(): void
    {
        $preset = $this->preset('core.text-block');

        $loader = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loader->method('load')->willReturn([$preset]);

        static::assertSame($preset, (new ContentSystemLayoutPresetRegistry([$loader]))->get('core.text-block'));
    }

    #[TestDox('throws for an unknown id on get')]
    public function testGetThrowsForUnknownId(): void
    {
        $this->expectExceptionObject(ContentSystemException::layoutPresetNotFound('core.unknown'));
        (new ContentSystemLayoutPresetRegistry([]))->get('core.unknown');
    }

    #[TestDox('throws DecorationPatternException when calling getDecorated')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemLayoutPresetRegistry::class));
        (new ContentSystemLayoutPresetRegistry([]))->getDecorated();
    }

    #[TestDox('throws when two loaders provide the same preset id')]
    public function testCrossLoaderDuplicateThrows(): void
    {
        $loaderA = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderA->method('load')->willReturn([$this->preset('core.dupe')]);

        $loaderB = static::createStub(AbstractContentSystemLayoutPresetLoader::class);
        $loaderB->method('load')->willReturn([$this->preset('core.dupe')]);

        $registry = new ContentSystemLayoutPresetRegistry([$loaderA, $loaderB]);

        $this->expectExceptionObject(ContentSystemException::layoutPresetDuplicate('core.dupe'));
        $registry->all();
    }

    private function preset(string $id): ContentSystemLayoutPresetSpecification
    {
        return new ContentSystemLayoutPresetSpecification($id, 'Name', null, null, []);
    }
}
