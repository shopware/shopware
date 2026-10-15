<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\PropertyMappingMutationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PropertyMappingMutationFactory::class)]
class PropertyMappingMutationFactoryTest extends TestCase
{
    public function testBuildsMappingMutationsFromTheSuppliedSourceAndTarget(): void
    {
        $factory = $this->factory();
        $tree = new StoredTree([
            new StoredElement(
                'element-1',
                'Sw:Text',
                properties: ['text' => StoredValue::ofString('fallback')],
            ),
        ]);

        $mapped = $factory->map(
            'product',
            'element-1',
            'text',
            MappingSourceReference::fromRootPath('product.name'),
        );

        $tree = $mapped->apply($tree);
        static::assertSame('product.name', $tree->roots[0]->contextDefinitions->getAllConsumers()['text']->source?->displayName());

        $unmapped = $factory->unmap('element-1', 'text');

        $tree = $unmapped->apply($tree);
        static::assertArrayNotHasKey('text', $tree->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame('fallback', $tree->roots[0]->property('text')?->asString());
    }

    private function factory(): PropertyMappingMutationFactory
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
            ->primitive('text', 'string', mappable: true)
            ->build();
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specification
        );

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            'product.name' => new MappingCandidate(
                'product.name',
                'product name',
                'The product name',
                'basic',
                'string',
                ContextType::Single,
            ),
        ]);

        return new PropertyMappingMutationFactory($typeRegistry, $candidateRegistry, new MappingTypeCompatibility());
    }
}
