<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mapping\DefaultMappingSeeder;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefaultMappingSeeder::class)]
class DefaultMappingSeederTest extends TestCase
{
    public function testSeedsACompatibleDefaultOnlyWhenTheRootOffersIt(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $seeder = $this->seeder($source);
        $element = new StoredElement(
            'element-1',
            'test:Media',
            dataRequirements: ['selectedMedia' => new DataRequirement('selectedMedia', 'entity', new StubLoaderConfig())],
        );

        $seeded = $seeder->seed(new StoredTree([$element], 'product'), [$element->id]);
        static::assertSame('product', $seeded->rootSource);
        $mapped = $seeded->roots[0];
        static::assertSame($source->jsonSerialize(), $mapped->contextDefinitions->getAllConsumers()['media']->source?->jsonSerialize());
        static::assertSame($element->dataRequirements, $mapped->dataRequirements);

        $otherLayout = $seeder->seed(new StoredTree([$element], 'category'), [$element->id])->roots[0];
        static::assertSame([], $otherLayout->contextDefinitions->getAllConsumers());
    }

    public function testDuplicateAfterDeliberateUnmappingDoesNotReceiveTheDefaultAgain(): void
    {
        $seeder = $this->seeder(MappingSourceReference::root('product', 'cover'));
        $element = new StoredElement('element-1', 'test:Media');
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));
        $pipeline = new MutationPipeline($diagnostics, new ContextConsumerMirror(), $seeder);

        $result = $pipeline->run(new DuplicateElement($element->id), new StoredTree([$element], 'product'), null);

        static::assertCount(2, $result->layout->roots);
        static::assertSame([], $result->layout->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame([], $result->layout->roots[1]->contextDefinitions->getAllConsumers());
    }

    #[DataProvider('occupiedDestinations')]
    public function testExistingConsumersPreventConflictingDefaults(string $contextKey, ?string $propertyAlias, bool $mapped): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $element = StoredElementBuilder::create('test:Media', 'element-1')
            ->withConsumer(
                $contextKey,
                ContextType::Single,
                propertyAlias: $propertyAlias,
                scope: $mapped ? ConsumerScope::Root : ConsumerScope::Parent,
                source: $mapped ? $source : null,
            )
            ->build();

        $seeded = $this->seeder($source)->seed(new StoredTree([$element], 'product'), [$element->id]);

        static::assertSame($element->contextDefinitions->getAllConsumers(), $seeded->roots[0]->contextDefinitions->getAllConsumers());
    }

    /**
     * @return iterable<string, array{string, string|null, bool}>
     */
    public static function occupiedDestinations(): iterable
    {
        yield 'ordinary consumer at the destination' => ['media', null, false];
        yield 'alias writes the destination' => ['selectedMedia', 'media', false];
        yield 'dotted consumer holds the same base' => ['media.url', null, false];
        yield 'existing authored mapping' => ['media', null, true];
        yield 'same consumer key with a different destination' => ['media', 'thumbnail', false];
    }

    public function testDottedConsumerAliasedToAnotherDestinationDoesNotBlockTheDefault(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $element = StoredElementBuilder::create('test:Media', 'element-1')
            ->withConsumer('media.url', ContextType::Single, propertyAlias: 'thumbnail')
            ->build();

        $seeded = $this->seeder($source)->seed(new StoredTree([$element], 'product'), [$element->id]);

        $consumers = $seeded->roots[0]->contextDefinitions->getAllConsumers();
        static::assertCount(2, $consumers);
        static::assertSame($element->contextDefinitions->getAllConsumers()['media.url'], $consumers['media.url']);
        static::assertSame($source, $consumers['media']->source);
    }

    public function testWrappingSeedsOnlyTheFreshContainer(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $registry = TestElementTypeRegistry::of(['test:Media' => $this->specification($source)]);
        $child = new StoredElement('existing-child', 'test:Media');
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));
        $pipeline = new MutationPipeline($diagnostics, new ContextConsumerMirror(), $this->seeder($source));

        $result = $pipeline->run(new WrapElements($registry, [$child->id], 'test:Media', 'content'), new StoredTree([$child], 'product'), null);

        $container = $result->layout->roots[0];
        static::assertNotSame($child->id, $container->id);
        static::assertSame($source, $container->contextDefinitions->getAllConsumers()['media']->source);
        static::assertSame($child->id, $container->slots['content'][0]->id);
        static::assertSame([], $container->slots['content'][0]->contextDefinitions->getAllConsumers());
    }

    public function testRejectsADefaultWithAnIncompatibleCandidateType(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $element = new StoredElement('element-1', 'test:Media');

        $result = $this->seeder($source, valueType: MediaEntity::class, propertyType: 'string')->seed(new StoredTree([$element], 'product'), [$element->id]);

        static::assertSame([], $result->roots[0]->contextDefinitions->getAllConsumers());
    }

    public function testSkipsPropertiesThatDoNotOptIntoMapping(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $element = new StoredElement('element-1', 'test:Media');

        $result = $this->seeder($source, mappable: false)->seed(new StoredTree([$element], 'product'), [$element->id]);

        static::assertSame([], $result->roots[0]->contextDefinitions->getAllConsumers());
    }

    public function testSeedsATargetedSlotChildWithoutMappingItsParent(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $child = new StoredElement('child', 'test:Media');
        $parent = new StoredElement('parent', 'test:Media', slots: ['content' => [$child]]);

        $result = $this->seeder($source)->seed(new StoredTree([$parent], 'product'), [$child->id]);

        static::assertSame([], $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame($source, $result->roots[0]->slots['content'][0]->contextDefinitions->getAllConsumers()['media']->source);
    }

    #[DataProvider('absentRootSourceProvider')]
    public function testDoesNotSeedWithoutARootSource(?string $rootSource): void
    {
        $element = new StoredElement('element-1', 'test:Media');
        $tree = new StoredTree([$element], $rootSource);

        static::assertSame($tree, $this->seeder(MappingSourceReference::root('product', 'cover'))->seed($tree, [$element->id]));
        static::assertSame([], $tree->roots[0]->contextDefinitions->getAllConsumers());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function absentRootSourceProvider(): iterable
    {
        yield 'null root source' => [null];
        yield 'empty root source' => [''];
    }

    public function testReturnsTheOriginalTreeWhenNoElementsWereScaffolded(): void
    {
        $tree = new StoredTree([new StoredElement('element-1', 'test:Media')], 'product');

        static::assertSame($tree, $this->seeder(MappingSourceReference::root('product', 'cover'))->seed($tree, []));
    }

    private function specification(MappingSourceReference $source, string $propertyType = MediaEntity::class, bool $mappable = true): ContentSystemElementTypeSpecification
    {
        $property = new PropertySpecification(
            'media',
            new PropertyType($propertyType, false, null, null),
            false,
            'Media',
            'Product media',
            null,
            $mappable,
            false,
            $source,
        );

        return new ContentSystemElementTypeSpecification(
            'test:Media',
            'Media',
            'Test media element',
            null,
            null,
            new CopilotSpecification('', []),
            ['media' => $property],
            [new SlotSpecification('content', null, [], '')],
        );
    }

    private function seeder(MappingSourceReference $source, string $valueType = MediaEntity::class, ?string $propertyType = null, bool $mappable = true): DefaultMappingSeeder
    {
        $specification = $this->specification($source, $propertyType ?? $valueType, $mappable);
        $types = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $types->method('has')->willReturn(true);
        $types->method('get')->willReturn($specification);
        $provider = new DefaultMappingTestProvider($source, $valueType);

        return new DefaultMappingSeeder(
            $types,
            new StoredMappingInspector(
                $types,
                new ContentSystemMappingCandidateRegistry([$provider], new NullLogger()),
                new MappingTypeCompatibility(),
                new MappingConsumers(),
                new InlineMappingTokenParser(),
            ),
        );
    }
}

/**
 * @internal
 */
class DefaultMappingTestProvider extends AbstractMappingCandidateProvider
{
    public function __construct(private readonly MappingSourceReference $source, private readonly string $valueType)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return [];
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource === 'product';
    }

    public function provide(string $rootSource): array
    {
        return [new MappingCandidate(
            $this->source->displayName(),
            'Media',
            'Product media',
            'media',
            $this->valueType,
            ContextType::Single,
            source: $this->source,
        )];
    }
}
