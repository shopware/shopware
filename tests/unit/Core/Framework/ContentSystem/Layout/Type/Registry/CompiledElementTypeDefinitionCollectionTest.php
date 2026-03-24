<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CompiledElementTypeDefinition;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\CompiledElementTypeDefinitionCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;

/**
 * @internal
 */
#[CoversClass(CompiledElementTypeDefinitionCollection::class)]
class CompiledElementTypeDefinitionCollectionTest extends TestCase
{
    #[TestDox('returns all added definitions as a list')]
    public function testAllReturnsAddedDefinitions(): void
    {
        $collection = new CompiledElementTypeDefinitionCollection();
        $collection->add($this->compiled('Sw:Content:Text', 'core'));
        $collection->add($this->compiled('Sw:Content:Image', 'core'));

        $all = $collection->all();
        static::assertCount(2, $all);
        static::assertSame('Sw:Content:Text', $all[0]->name());
        static::assertSame('Sw:Content:Image', $all[1]->name());
    }

    #[TestDox('returns empty list when nothing was added')]
    public function testAllReturnsEmptyListByDefault(): void
    {
        $collection = new CompiledElementTypeDefinitionCollection();

        static::assertSame([], $collection->all());
    }

    #[TestDox('throws on duplicate type name with source information')]
    public function testThrowsOnDuplicateName(): void
    {
        $collection = new CompiledElementTypeDefinitionCollection();
        $collection->add($this->compiled('Sw:Content:Text', 'core'));

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'core', 'plugin:MyPlugin')
        );

        $collection->add($this->compiled('Sw:Content:Text', 'plugin:MyPlugin'));
    }

    private function compiled(string $name, string $source): CompiledElementTypeDefinition
    {
        return new CompiledElementTypeDefinition(
            new ContentSystemElementTypeSpecification(
                $name,
                $name,
                '',
                'test',
                null,
                null,
                new CopilotSpecification('', []),
                [],
                [],
            ),
            $source,
        );
    }
}
