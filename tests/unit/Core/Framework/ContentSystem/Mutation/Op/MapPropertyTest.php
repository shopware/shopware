<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MapProperty;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MapProperty::class)]
class MapPropertyTest extends TestCase
{
    #[DataProvider('unboundSources')]
    public function testRejectsMappingAnUnboundTree(?string $rootSource): void
    {
        $tree = new StoredTree([new StoredElement('element-1', 'Sw:Text')], $rootSource);

        $this->expectExceptionObject(ContentSystemException::unknownRootSource(''));

        $this->map('text', 'product.name')->apply($tree);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unboundSources(): iterable
    {
        yield 'absent source' => [null];
        yield 'empty source' => [''];
    }

    public function testOneSourcePathCanFeedSeveralProperties(): void
    {
        $tree = new StoredTree([
            StoredElementBuilder::create('Sw:Text', 'element-1')
                ->withProperty('text', 'Text fallback')
                ->withProperty('headline', 'Headline fallback')
                ->build(),
        ], 'product');

        $tree = $this->map('text', 'product.name')->apply($tree);
        $tree = $this->map('headline', 'product.name')->apply($tree);

        $consumers = $tree->roots[0]->contextDefinitions->getAllConsumers();
        static::assertSame('product.name', $consumers['text']->source?->displayName());
        static::assertSame('product.name', $consumers['headline']->source?->displayName());
    }

    public function testMappingTheSamePropertyAgainReplacesOnlyThatMapping(): void
    {
        $tree = new StoredTree([new StoredElement('element-1', 'Sw:Text')], 'product');
        $tree = $this->map('text', 'product.name')->apply($tree);
        $tree = $this->map('text', 'product.metaTitle')->apply($tree);

        $consumers = $tree->roots[0]->contextDefinitions->getAllConsumers();

        static::assertCount(1, $consumers);
        static::assertSame('product.metaTitle', $consumers['text']->source?->displayName());
    }

    public function testMappingPreservesExistingDataRequirements(): void
    {
        $element = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withDataRequirement('selectedProduct', 'entity', new StubLoaderConfig())
            ->build();

        $mapped = $this->map('text', 'product.name')->apply(new StoredTree([$element], 'product'))->roots[0];

        static::assertSame($element->dataRequirements, $mapped->dataRequirements);
        static::assertSame('product.name', $mapped->contextDefinitions->getAllConsumers()['text']->source?->displayName());
    }

    public function testRejectsAnIncompatibleCandidateBeforeWriting(): void
    {
        $tree = new StoredTree([new StoredElement('element-1', 'Sw:Text')], 'product');

        $this->expectExceptionObject(ContentSystemException::mappingTypeMismatch('text', 'string', MediaEntity::class));

        $this->map('text', 'product.cover')->apply($tree);
    }

    private function map(string $propertyKey, string $sourcePath): MapProperty
    {
        return $this->mapSource($propertyKey, MappingSourceReference::fromRootPath($sourcePath));
    }

    private function mapSource(string $propertyKey, MappingSourceReference $source): MapProperty
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
            ->primitive('text', 'string', mappable: true)
            ->primitive('headline', 'string', mappable: true)
            ->build();

        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specification
        );

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            'product.name' => $this->candidate('product.name'),
            'product.metaTitle' => $this->candidate('product.metaTitle'),
            'product.cover' => new MappingCandidate('product.cover', 'label', 'description', 'media', MediaEntity::class),
        ]);

        return new MapProperty(
            $typeRegistry,
            new StoredMappingInspector(
                $typeRegistry,
                $candidateRegistry,
                new MappingTypeCompatibility(),
                new MappingConsumers(),
                new InlineMappingTokenParser(),
            ),
            'element-1',
            $propertyKey,
            $source,
        );
    }

    private function candidate(string $path): MappingCandidate
    {
        return new MappingCandidate($path, 'label', 'description', 'basic', 'string');
    }
}
