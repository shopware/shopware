<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeRegistry::class)]
class ContentSystemElementTypeRegistryTest extends TestCase
{
    #[TestDox('returns all compiled specifications')]
    public function testAllReturnsAllRegisteredSpecifications(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentSystemElementTypeRegistry([$def], []);

        $all = $registry->all();
        static::assertCount(1, $all);
        static::assertArrayHasKey('Sw:Content:Text', $all);
    }

    #[TestDox('returns both compiled and runtime specifications after loading')]
    public function testAllReturnsBothCompiledAndRuntimeSpecifications(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtime]);

        $registry = new ContentSystemElementTypeRegistry([$compiled], [$loader]);

        static::assertCount(2, $registry->all());
    }

    #[TestDox('returns true for a registered type')]
    public function testHasReturnsTrueForRegisteredType(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentSystemElementTypeRegistry([$def], []);

        static::assertTrue($registry->has('Sw:Content:Text'));
    }

    #[TestDox('returns false for an unknown type')]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $registry = new ContentSystemElementTypeRegistry([], []);

        static::assertFalse($registry->has('Sw:Unknown:Type'));
    }

    #[TestDox('returns the specification for a registered type')]
    public function testGetReturnsSpecificationForRegisteredType(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentSystemElementTypeRegistry([$def], []);

        static::assertSame($def, $registry->get('Sw:Content:Text'));
    }

    #[TestDox('throws for unknown type on get')]
    public function testGetThrowsForUnknownType(): void
    {
        $registry = new ContentSystemElementTypeRegistry([], []);

        $this->expectExceptionObject(ContentSystemException::elementTypeNotFound('Sw:Unknown:Type'));
        $registry->get('Sw:Unknown:Type');
    }

    #[TestDox('loads runtime specifications on first query')]
    public function testLoadsRuntimeSpecificationsOnFirstQuery(): void
    {
        $runtimeDef = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtimeDef]);

        $registry = new ContentSystemElementTypeRegistry([], [$loader]);

        static::assertTrue($registry->has('App:Demo:Hero'));
    }

    #[TestDox('does not reload runtime specifications on subsequent queries')]
    public function testDoesNotReloadOnSubsequentQueries(): void
    {
        $runtimeDef = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = $this->createMock(AbstractContentSystemElementTypeLoader::class);
        $loader->expects($this->once())->method('load')->willReturn([$runtimeDef]);

        $registry = new ContentSystemElementTypeRegistry([], [$loader]);
        $registry->has('App:Demo:Hero');
        $registry->has('App:Demo:Hero');
    }

    #[TestDox('preserves compiled specifications after reset')]
    public function testResetPreservesCompiledSpecifications(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtime]);

        $registry = new ContentSystemElementTypeRegistry([$compiled], [$loader]);
        $registry->all();
        $registry->reset();

        static::assertTrue($registry->has('Sw:Content:Text'));
    }

    #[TestDox('reloads runtime specifications from loaders after reset')]
    public function testResetTriggersReloadFromLoaders(): void
    {
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = $this->createMock(AbstractContentSystemElementTypeLoader::class);
        $loader->expects($this->exactly(2))->method('load')->willReturn([$runtime]);

        $registry = new ContentSystemElementTypeRegistry([], [$loader]);
        $registry->all();
        $registry->reset();
        $registry->all();
    }

    #[TestDox('throws for duplicate registration in compiled specifications')]
    public function testDuplicateCompiledSpecificationThrows(): void
    {
        $def1 = $this->createSpec('Sw:Content:Text', 'Text');
        $def2 = $this->createSpec('Sw:Content:Text', 'Text 2');

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'compiled', 'compiled')
        );
        new ContentSystemElementTypeRegistry([$def1, $def2], []);
    }

    #[TestDox('throws when runtime loader registers an already compiled type')]
    public function testRuntimeLoaderDuplicateWithCompiledThrows(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('Sw:Content:Text', 'Text');

        $loader = new FixedTypeLoader([$runtime]);

        $registry = new ContentSystemElementTypeRegistry([$compiled], [$loader]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'compiled', FixedTypeLoader::class)
        );
        $registry->all();
    }

    private function createSpec(string $name, string $label): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            $name,
            $label,
            '',
            'test',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
        );
    }
}

/**
 * @internal
 */
class FixedTypeLoader extends AbstractContentSystemElementTypeLoader
{
    /**
     * @param list<ContentSystemElementTypeSpecification> $definitions
     */
    public function __construct(private readonly array $definitions)
    {
    }

    public function load(): array
    {
        return $this->definitions;
    }
}
