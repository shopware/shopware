<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CachedContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemElementTypeRegistry::class)]
class CachedContentSystemElementTypeRegistryTest extends TestCase
{
    #[TestDox('delegates to inner registry on cache miss and caches the result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $spec = $this->createSpec('Sw:Content:Text');
        $inner = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['Sw:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $result = $registry->all();

        static::assertArrayHasKey('Sw:Content:Text', $result);
        static::assertSame($spec, $result['Sw:Content:Text']);
    }

    #[TestDox('returns cached result on second all() call without calling inner again')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $spec = $this->createSpec('Sw:Content:Text');
        $inner = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['Sw:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $registry->all();
        $registry->all();
    }

    #[TestDox('returns true for a type present in the registry')]
    public function testHasReturnsTrueForCachedType(): void
    {
        $spec = $this->createSpec('Sw:Content:Text');
        $inner = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['Sw:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertTrue($registry->has('Sw:Content:Text'));
    }

    #[TestDox('returns the specification for a known type')]
    public function testGetReturnsSpecificationFromCache(): void
    {
        $spec = $this->createSpec('Sw:Content:Text');
        $inner = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['Sw:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertSame($spec, $registry->get('Sw:Content:Text'));
    }

    #[TestDox('forces re-delegation to inner registry after invalidation')]
    public function testInvalidateClearsCache(): void
    {
        $spec = $this->createSpec('Sw:Content:Text');
        $inner = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['Sw:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $registry->all();
        $registry->invalidate();
        $registry->all();
    }

    #[TestDox('returns false for an unknown type')]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $inner = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn([]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertFalse($registry->has('Sw:Unknown:Type'));
    }

    #[TestDox('throws for an unknown type')]
    public function testGetThrowsForUnknownType(): void
    {
        $inner = $this->createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn([]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $this->expectExceptionObject(ContentSystemException::elementTypeNotFound('Sw:Unknown:Type'));
        $registry->get('Sw:Unknown:Type');
    }

    private function createSpec(string $name): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            $name,
            $name,
            '',
            'test',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
            'test',
        );
    }
}
