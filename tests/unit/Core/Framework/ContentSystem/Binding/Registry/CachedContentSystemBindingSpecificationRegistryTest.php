<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\CachedContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CachedContentSystemBindingSpecificationRegistry::class)]
class CachedContentSystemBindingSpecificationRegistryTest extends TestCase
{
    #[TestDox('delegates to the inner registry on a cache miss and returns its result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $specification = $this->specification('media-picker');
        $inner = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->method('all')->willReturn(['core:media-picker' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        static::assertSame(['core:media-picker' => $specification], $registry->all());
    }

    #[TestDox('serves the cached result on the second call under the content_system.binding_specifications key without re-delegating')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $specification = $this->specification('media-picker');
        $inner = $this->createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['core:media-picker' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        $first = $registry->all();
        $second = $registry->all();

        // The cache pool round-trips values through serialization, so assert value equality, not identity
        static::assertEquals(['core:media-picker' => $specification], $second);
        static::assertEquals($first, $second);
    }

    #[TestDox('re-delegates to the inner registry after invalidation')]
    public function testInvalidateClearsTheCache(): void
    {
        $specification = $this->specification('media-picker');
        $inner = $this->createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['core:media-picker' => $specification]);

        $registry = new CachedContentSystemBindingSpecificationRegistry($inner, new ArrayAdapter());

        $registry->all();
        $registry->invalidate();

        static::assertEquals(['core:media-picker' => $specification], $registry->all());
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

    private function specification(string $id): BindingSpecification
    {
        return new BindingSpecification($id, 'media-gallery', 'label', [], [], 'core');
    }
}
