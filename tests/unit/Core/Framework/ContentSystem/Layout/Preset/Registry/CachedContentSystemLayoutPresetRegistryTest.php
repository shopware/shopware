<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPreset;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\AbstractContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\CachedContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedContentSystemLayoutPresetRegistry::class)]
class CachedContentSystemLayoutPresetRegistryTest extends TestCase
{
    #[TestDox('delegates to inner registry on cache miss and caches the result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $preset = $this->createPreset('core.text-block');
        $inner = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->method('all')->willReturn(['core.text-block' => $preset]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        $result = $registry->all();

        static::assertArrayHasKey('core.text-block', $result);
        static::assertSame($preset, $result['core.text-block']);
    }

    #[TestDox('returns cached result on second all() call without calling inner again')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $preset = $this->createPreset('core.text-block');
        $inner = $this->createMock(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['core.text-block' => $preset]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        $registry->all();
        $registry->all();
    }

    #[TestDox('returns true for a preset present in the registry')]
    public function testHasReturnsTrueForCachedId(): void
    {
        $inner = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->method('all')->willReturn(['core.text-block' => $this->createPreset('core.text-block')]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        static::assertTrue($registry->has('core.text-block'));
    }

    #[TestDox('returns the preset for a known id')]
    public function testGetReturnsPresetFromCache(): void
    {
        $preset = $this->createPreset('core.text-block');
        $inner = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->method('all')->willReturn(['core.text-block' => $preset]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        static::assertSame($preset, $registry->get('core.text-block'));
    }

    #[TestDox('forces re-delegation to inner registry after invalidation')]
    public function testInvalidateClearsCache(): void
    {
        $preset = $this->createPreset('core.text-block');
        $inner = $this->createMock(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['core.text-block' => $preset]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        $registry->all();
        $registry->invalidate();
        $registry->all();
    }

    #[TestDox('returns false for an unknown id')]
    public function testHasReturnsFalseForUnknownId(): void
    {
        $inner = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->method('all')->willReturn([]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        static::assertFalse($registry->has('core.unknown'));
    }

    #[TestDox('throws for an unknown id')]
    public function testGetThrowsForUnknownId(): void
    {
        $inner = static::createStub(AbstractContentSystemLayoutPresetRegistry::class);
        $inner->method('all')->willReturn([]);

        $registry = new CachedContentSystemLayoutPresetRegistry($inner, new ArrayAdapter());

        $this->expectExceptionObject(ContentSystemException::layoutPresetNotFound('core.unknown'));
        $registry->get('core.unknown');
    }

    private function createPreset(string $id): LayoutPreset
    {
        return new LayoutPreset($id, 'Name', null, null, []);
    }
}
