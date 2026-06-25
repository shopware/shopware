<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\CachedContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemStyleOptionRegistry::class)]
class CachedContentSystemStyleOptionRegistryTest extends TestCase
{
    #[TestDox('delegates to the inner registry on a cache miss and returns its result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $option = $this->option('col-span');
        $inner = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $inner->method('all')->willReturn(['col-span' => $option]);

        $registry = new CachedContentSystemStyleOptionRegistry($inner, new ArrayAdapter());

        static::assertSame(['col-span' => $option], $registry->all());
    }

    #[TestDox('serves the cached result on the second call without re-delegating')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $option = $this->option('col-span');
        $inner = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['col-span' => $option]);

        $registry = new CachedContentSystemStyleOptionRegistry($inner, new ArrayAdapter());

        $first = $registry->all();
        $second = $registry->all();

        // The cache pool round-trips values through serialization, so assert value equality, not identity
        static::assertEquals(['col-span' => $option], $second);
        static::assertEquals($first, $second);
    }

    #[TestDox('re-delegates to the inner registry after invalidation')]
    public function testInvalidateClearsCache(): void
    {
        $inner = $this->createMock(AbstractContentSystemStyleOptionRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['col-span' => $this->option('col-span')]);

        $registry = new CachedContentSystemStyleOptionRegistry($inner, new ArrayAdapter());

        $registry->all();
        $registry->invalidate();
        $registry->all();
    }

    #[TestDox('exposes the decorated inner registry')]
    public function testGetDecoratedReturnsInner(): void
    {
        $inner = static::createStub(AbstractContentSystemStyleOptionRegistry::class);

        $registry = new CachedContentSystemStyleOptionRegistry($inner, new ArrayAdapter());

        static::assertSame($inner, $registry->getDecorated());
    }

    private function option(string $name): StyleOptionSpecification
    {
        return new StyleOptionSpecification($name, new StyleOptionValueType('integer', null, null, null, null), null, 'core');
    }
}
