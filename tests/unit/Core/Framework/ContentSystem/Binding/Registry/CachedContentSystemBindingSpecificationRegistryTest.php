<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\CachedContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemBindingSpecificationRegistry::class)]
class CachedContentSystemBindingSpecificationRegistryTest extends TestCase
{
    #[TestDox('delegates to the inner registry on a cache miss and returns its result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $specification = $this->specification('from-media-library');
        $inner = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->method('all')->willReturn(['core:from-media-library' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        static::assertSame(['core:from-media-library' => $specification], $registry->all());
    }

    #[TestDox('serves the cached result on the second call under the content_system.binding_specifications key without re-delegating')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $specification = $this->specification('from-media-library');
        $inner = $this->createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['core:from-media-library' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        $first = $registry->all();
        $second = $registry->all();

        // The cache pool round-trips values through serialization, so assert value equality, not identity
        static::assertEquals(['core:from-media-library' => $specification], $second);
        static::assertEquals($first, $second);
    }

    #[TestDox('re-delegates to the inner registry after invalidation')]
    public function testInvalidateClearsTheCache(): void
    {
        $specification = $this->specification('from-media-library');
        $inner = $this->createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['core:from-media-library' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        $registry->all();
        $registry->invalidate();

        static::assertEquals(['core:from-media-library' => $specification], $registry->all());
    }

    #[TestDox('caches and returns an empty specification set when the inner registry provides none')]
    public function testAllCachesEmptyResultFromInner(): void
    {
        $inner = $this->createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn([]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        static::assertSame([], $registry->all());
        // Second call proves the empty result is cached rather than re-delegated (once() above).
        static::assertSame([], $registry->all());
    }

    #[TestDox('returns the inner registry from getDecorated')]
    public function testGetDecoratedReturnsInner(): void
    {
        $inner = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        static::assertSame($inner, $registry->getDecorated());
    }

    #[TestDox('throws when invalidate is called on the leaf registry, per the decoration-pattern contract')]
    public function testInvalidateOnLeafRegistryThrows(): void
    {
        // invalidate() is defined on the abstract base (self::class), inherited unchanged by the leaf;
        // only the cached decorator overrides it. So the exception names the abstract base class.
        $this->expectExceptionObject(new DecorationPatternException(AbstractContentSystemBindingSpecificationRegistry::class));

        (new ContentSystemBindingSpecificationRegistry([]))->invalidate();
    }

    private function specification(string $id): BindingSpecification
    {
        return new BindingSpecification($id, 'media-gallery', 'label', [], [], 'core');
    }
}
