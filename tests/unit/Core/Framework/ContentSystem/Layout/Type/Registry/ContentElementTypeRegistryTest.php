<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;

/**
 * @internal
 */
#[CoversClass(ContentElementTypeRegistry::class)]
class ContentElementTypeRegistryTest extends TestCase
{
    #[TestDox('returns all compiled specifications')]
    public function testAllReturnsAllRegisteredSpecifications(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentElementTypeRegistry([$def], []);

        $all = $registry->all();
        static::assertCount(1, $all);
        static::assertArrayHasKey('Sw:Content:Text', $all);
    }

    #[TestDox('returns both compiled and runtime specifications after loading')]
    public function testAllReturnsBothCompiledAndRuntimeSpecifications(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtime]);

        $registry = new ContentElementTypeRegistry([$compiled], [$loader]);

        static::assertCount(2, $registry->all());
    }

    #[TestDox('returns true for a registered type')]
    public function testHasReturnsTrueForRegisteredType(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentElementTypeRegistry([$def], []);

        static::assertTrue($registry->has('Sw:Content:Text'));
    }

    #[TestDox('returns false for an unknown type')]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $registry = new ContentElementTypeRegistry([], []);

        static::assertFalse($registry->has('Sw:Unknown:Type'));
    }

    #[TestDox('returns the specification for a registered type')]
    public function testGetReturnsSpecificationForRegisteredType(): void
    {
        $def = $this->createSpec('Sw:Content:Text', 'Text');
        $registry = new ContentElementTypeRegistry([$def], []);

        static::assertSame($def, $registry->get('Sw:Content:Text'));
    }

    #[TestDox('throws for unknown type on get')]
    public function testGetThrowsForUnknownType(): void
    {
        $registry = new ContentElementTypeRegistry([], []);

        $this->expectExceptionObject(ContentSystemException::elementTypeNotFound('Sw:Unknown:Type'));
        $registry->get('Sw:Unknown:Type');
    }

    #[TestDox('loads runtime specifications on first query')]
    public function testLoadsRuntimeSpecificationsOnFirstQuery(): void
    {
        $runtimeDef = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtimeDef]);

        $registry = new ContentElementTypeRegistry([], [$loader]);

        static::assertTrue($registry->has('App:Demo:Hero'));
    }

    #[TestDox('does not reload runtime specifications on subsequent queries')]
    public function testDoesNotReloadOnSubsequentQueries(): void
    {
        $runtimeDef = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = $this->createMock(AbstractContentElementTypeLoader::class);
        $loader->expects($this->once())->method('load')->willReturn([$runtimeDef]);

        $registry = new ContentElementTypeRegistry([], [$loader]);
        $registry->has('App:Demo:Hero');
        $registry->has('App:Demo:Hero');
    }

    #[TestDox('preserves compiled specifications after reset')]
    public function testResetPreservesCompiledSpecifications(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = static::createStub(AbstractContentElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtime]);

        $registry = new ContentElementTypeRegistry([$compiled], [$loader]);
        $registry->all();
        $registry->reset();

        static::assertTrue($registry->has('Sw:Content:Text'));
    }

    #[TestDox('reloads runtime specifications from loaders after reset')]
    public function testResetTriggersReloadFromLoaders(): void
    {
        $runtime = $this->createSpec('App:Demo:Hero', 'Hero');

        $loader = $this->createMock(AbstractContentElementTypeLoader::class);
        $loader->expects($this->exactly(2))->method('load')->willReturn([$runtime]);

        $registry = new ContentElementTypeRegistry([], [$loader]);
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
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'Sw:Content:Text', 'Sw:Content:Text')
        );
        new ContentElementTypeRegistry([$def1, $def2], []);
    }

    #[TestDox('throws when runtime loader registers an already compiled type')]
    public function testRuntimeLoaderDuplicateWithCompiledThrows(): void
    {
        $compiled = $this->createSpec('Sw:Content:Text', 'Text');
        $runtime = $this->createSpec('Sw:Content:Text', 'Text');

        $loader = static::createStub(AbstractContentElementTypeLoader::class);
        $loader->method('load')->willReturn([$runtime]);

        $registry = new ContentElementTypeRegistry([$compiled], [$loader]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'Sw:Content:Text', 'Sw:Content:Text')
        );
        $registry->all();
    }

    private function createSpec(string $name, string $label): ContentElementTypeSpecification
    {
        return new ContentElementTypeSpecification(
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
