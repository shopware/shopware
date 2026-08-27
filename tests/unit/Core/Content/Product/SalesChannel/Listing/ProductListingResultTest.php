<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingResult::class)]
class ProductListingResultTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - Remove together with the EntitySearchResult inheritance; the copied parent state disappears with it
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $listing = ProductListingResult::fromSearchResult($source);

        static::assertSame($source->getTotal(), $listing->getTotal());
        static::assertSame($source->getEntities(), $listing->getEntities());
        static::assertSame($source->getCriteria(), $listing->getCriteria());
        static::assertSame($source->getContext(), $listing->getContext());
        static::assertSame($source->getAggregations(), $listing->getAggregations());
    }

    public function testFromSearchResultSetsListingSpecificFields(): void
    {
        $sortings = new ProductSortingCollection();

        $listing = ProductListingResult::fromSearchResult(
            $this->createSearchResult(),
            availableSortings: $sortings,
            sorting: 'name-asc',
            currentFilters: ['category' => 'electronics'],
            streamId: 'stream-id-1',
        );

        static::assertSame($sortings, $listing->getAvailableSortings());
        static::assertSame('name-asc', $listing->getSorting());
        static::assertSame(['category' => 'electronics'], $listing->getCurrentFilters());
        static::assertSame('stream-id-1', $listing->getStreamId());
    }

    public function testFromSearchResultUsesDefaultsWhenExtrasOmitted(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        static::assertNull($listing->getSorting());
        static::assertSame([], $listing->getCurrentFilters());
        static::assertNull($listing->getStreamId());
    }

    public function testFromSearchResultKeepsPaginationExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            42,
            new ProductCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $listing = ProductListingResult::fromSearchResult($source);

        static::assertSame(10, $listing->getLimit());
        static::assertSame(3, $listing->getPage());
        static::assertSame($source->getExtension('custom'), $listing->getExtension('custom'));
        static::assertTrue($listing->hasState('custom-state'));
    }

    public function testFromSearchResultExposesThePassedResultAsSource(): void
    {
        $source = $this->createSearchResult();

        $listing = ProductListingResult::fromSearchResult($source);

        static::assertSame($source, $listing->getSource());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the lazy fallback in getSource()
     */
    public function testGetSourceIsBuiltLazilyForInstancesCreatedViaCreateFrom(): void
    {
        $source = $this->createSearchResult();

        $listing = ProductListingResult::createFrom($source);

        $searchResult = $listing->getSource();

        static::assertNotSame($source, $searchResult);
        static::assertSame($source->getEntities(), $searchResult->getEntities());
        static::assertSame($source->getTotal(), $searchResult->getTotal());
        static::assertSame($source->getCriteria(), $searchResult->getCriteria());
        static::assertSame($source->getContext(), $searchResult->getContext());
        static::assertSame($searchResult, $listing->getSource());
    }

    public function testJsonSerializeDoesNotContainTheSource(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        $vars = $listing->jsonSerialize();

        static::assertArrayNotHasKey('source', $vars);
        static::assertArrayHasKey('currentFilters', $vars);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated methods inherited from EntitySearchResult
     *
     * @param \Closure(ProductListingResult): mixed $call
     */
    #[DataProvider('deprecatedCollectionMethodCases')]
    public function testDeprecatedCollectionMethodsPointToTheSource(\Closure $call, string $method, string $substitute): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ProductListingResult::class, $method, 'v6.8.0.0', $substitute)
        ));

        $call($listing);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated assignRecursive() override
     */
    public function testAssignRecursivePointsToTheSource(): void
    {
        $listing = ProductListingResult::fromSearchResult($this->createSearchResult());

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "%s::assignRecursive()" is deprecated. As of v6.8.0.0 it will no longer add entities to the result, but fall back to "Struct::assignRecursive()", which has no effect on the readonly result. To add entities, use "getSource()->getEntities()->assignRecursive()" instead.',
            ProductListingResult::class
        )));

        $listing->assignRecursive([]);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with the deprecated methods inherited from EntitySearchResult
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedCollectionMethodsStillDelegateToTheParent(): void
    {
        $id = Uuid::randomHex();
        $product = new ProductEntity();
        $product->setId($id);
        $product->setUniqueIdentifier($id);

        $listing = ProductListingResult::fromSearchResult(
            $this->createSearchResult(new ProductCollection([$product]))
        );

        static::assertCount(1, $listing);
        static::assertFalse($listing->isEmpty());
        static::assertSame($product, $listing->first());
        static::assertSame(1, $listing->getTotal());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with testDeprecatedCollectionMethodsPointToTheSource()
     *
     * @return \Generator<string, array{\Closure(ProductListingResult): mixed, string, string}>
     */
    public static function deprecatedCollectionMethodCases(): \Generator
    {
        yield 'getEntities()' => [
            static fn (ProductListingResult $listing) => $listing->getEntities(),
            'getEntities',
            'getSource()->getEntities()',
        ];

        yield 'getTotal()' => [
            static fn (ProductListingResult $listing) => $listing->getTotal(),
            'getTotal',
            'getSource()->getTotal()',
        ];

        yield 'getAggregations()' => [
            static fn (ProductListingResult $listing) => $listing->getAggregations(),
            'getAggregations',
            'getSource()->getAggregations()',
        ];

        yield 'getCriteria()' => [
            static fn (ProductListingResult $listing) => $listing->getCriteria(),
            'getCriteria',
            'getSource()->getCriteria()',
        ];

        yield 'getContext()' => [
            static fn (ProductListingResult $listing) => $listing->getContext(),
            'getContext',
            'getSource()->getContext()',
        ];

        yield 'getEntity()' => [
            static fn (ProductListingResult $listing) => $listing->getEntity(),
            'getEntity',
            'getSource()->getEntity()',
        ];

        yield 'filter()' => [
            static fn (ProductListingResult $listing) => $listing->filter(static fn () => true),
            'filter',
            'getSource()->getEntities()->filter()',
        ];

        yield 'slice()' => [
            static fn (ProductListingResult $listing) => $listing->slice(0),
            'slice',
            'getSource()->getEntities()->slice()',
        ];

        yield 'getAt()' => [
            static fn (ProductListingResult $listing) => $listing->getAt(0),
            'getAt',
            'getSource()->getEntities()->getAt()',
        ];

        yield 'fill()' => [
            static fn (ProductListingResult $listing) => $listing->fill([]),
            'fill',
            'getSource()->getEntities()->fill()',
        ];

        yield 'set()' => [
            static fn (ProductListingResult $listing) => $listing->set('key', new ProductEntity()),
            'set',
            'getSource()->getEntities()->set()',
        ];

        yield 'get()' => [
            static fn (ProductListingResult $listing) => $listing->get('key'),
            'get',
            'getSource()->getEntities()->get()',
        ];

        yield 'count()' => [
            static fn (ProductListingResult $listing) => $listing->count(),
            'count',
            'getSource()->getEntities()->count()',
        ];

        yield 'isEmpty()' => [
            static fn (ProductListingResult $listing) => $listing->isEmpty(),
            'isEmpty',
            'getSource()->getEntities()->isEmpty()',
        ];

        yield 'getKeys()' => [
            static fn (ProductListingResult $listing) => $listing->getKeys(),
            'getKeys',
            'getSource()->getEntities()->getKeys()',
        ];

        yield 'has()' => [
            static fn (ProductListingResult $listing) => $listing->has('key'),
            'has',
            'getSource()->getEntities()->has()',
        ];

        yield 'map()' => [
            static fn (ProductListingResult $listing) => $listing->map(static fn () => null),
            'map',
            'getSource()->getEntities()->map()',
        ];

        yield 'reduce()' => [
            static fn (ProductListingResult $listing) => $listing->reduce(static fn ($carry) => $carry),
            'reduce',
            'getSource()->getEntities()->reduce()',
        ];

        yield 'fmap()' => [
            static fn (ProductListingResult $listing) => $listing->fmap(static fn () => null),
            'fmap',
            'getSource()->getEntities()->fmap()',
        ];

        yield 'flatMap()' => [
            static fn (ProductListingResult $listing) => $listing->flatMap(static fn () => null),
            'flatMap',
            'getSource()->getEntities()->flatMap()',
        ];

        yield 'sort()' => [
            static fn (ProductListingResult $listing) => $listing->sort(static fn () => 0),
            'sort',
            'getSource()->getEntities()->sort()',
        ];

        yield 'filterInstance()' => [
            static fn (ProductListingResult $listing) => $listing->filterInstance(ProductEntity::class),
            'filterInstance',
            'getSource()->getEntities()->filterInstance()',
        ];

        yield 'getElements()' => [
            static fn (ProductListingResult $listing) => $listing->getElements(),
            'getElements',
            'getSource()->getEntities()->getElements()',
        ];

        yield 'first()' => [
            static fn (ProductListingResult $listing) => $listing->first(),
            'first',
            'getSource()->getEntities()->first()',
        ];

        yield 'firstWhere()' => [
            static fn (ProductListingResult $listing) => $listing->firstWhere(static fn () => true),
            'firstWhere',
            'getSource()->getEntities()->firstWhere()',
        ];

        yield 'last()' => [
            static fn (ProductListingResult $listing) => $listing->last(),
            'last',
            'getSource()->getEntities()->last()',
        ];

        yield 'remove()' => [
            static fn (ProductListingResult $listing) => $listing->remove('key'),
            'remove',
            'getSource()->getEntities()->remove()',
        ];

        yield 'getIterator()' => [
            static fn (ProductListingResult $listing) => $listing->getIterator(),
            'getIterator',
            'getSource()->getEntities()',
        ];

        yield 'getIds()' => [
            static fn (ProductListingResult $listing) => $listing->getIds(),
            'getIds',
            'getSource()->getEntities()->getIds()',
        ];

        yield 'filterByProperty()' => [
            static fn (ProductListingResult $listing) => $listing->filterByProperty('id', 'value'),
            'filterByProperty',
            'getSource()->getEntities()->filterByProperty()',
        ];

        yield 'filterAndReduceByProperty()' => [
            static fn (ProductListingResult $listing) => $listing->filterAndReduceByProperty('id', 'value'),
            'filterAndReduceByProperty',
            'getSource()->getEntities()->filterAndReduceByProperty()',
        ];

        yield 'merge()' => [
            static fn (ProductListingResult $listing) => $listing->merge(new ProductCollection()),
            'merge',
            'getSource()->getEntities()->merge()',
        ];

        yield 'insert()' => [
            static fn (ProductListingResult $listing) => $listing->insert(0, new ProductEntity()),
            'insert',
            'getSource()->getEntities()->insert()',
        ];

        yield 'getList()' => [
            static fn (ProductListingResult $listing) => $listing->getList([]),
            'getList',
            'getSource()->getEntities()->getList()',
        ];

        yield 'sortByIdArray()' => [
            static fn (ProductListingResult $listing) => $listing->sortByIdArray([]),
            'sortByIdArray',
            'getSource()->getEntities()->sortByIdArray()',
        ];

        yield 'getCustomFieldsValues()' => [
            static fn (ProductListingResult $listing) => $listing->getCustomFieldsValues(),
            'getCustomFieldsValues',
            'getSource()->getEntities()->getCustomFieldsValues()',
        ];

        yield 'getCustomFieldsValue()' => [
            static fn (ProductListingResult $listing) => $listing->getCustomFieldsValue('field'),
            'getCustomFieldsValue',
            'getSource()->getEntities()->getCustomFieldsValue()',
        ];

        yield 'setCustomFields()' => [
            static fn (ProductListingResult $listing) => $listing->setCustomFields([]),
            'setCustomFields',
            'getSource()->getEntities()->setCustomFields()',
        ];
    }

    /**
     * Every public method inherited from the EntitySearchResult chain vanishes in v6.8.0 when the class stops
     * extending it. Each one must therefore either be declared on ProductListingResult itself (kept or deprecated
     * with a substitute) or be deprecated at its declaring class — otherwise it would disappear without any signal.
     *
     * @deprecated tag:v6.8.0 - Remove together with the EntitySearchResult inheritance; nothing outside Struct is inherited afterwards
     */
    public function testInheritedPublicApiIsDeclaredLocallyOrDeprecated(): void
    {
        // the final parent constructor can be neither overridden nor deprecated; its v6.8.0 change is documented for EntitySearchResult.
        // The state methods are intentionally kept: the v6.8.0 rework adds StateAwareTrait to this class itself.
        $allowList = ['__construct', 'addState', 'removeState', 'hasState', 'getStates', 'state'];

        $struct = new \ReflectionClass(Struct::class);

        foreach ((new \ReflectionClass(ProductListingResult::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === ProductListingResult::class) {
                continue;
            }

            if (\in_array($method->getName(), $allowList, true)) {
                continue;
            }

            // Struct stays the parent class in v6.8.0, so its API survives the hierarchy change
            if ($struct->hasMethod($method->getName())) {
                continue;
            }

            static::assertStringContainsString(
                '@deprecated tag:v6.8.0',
                (string) $method->getDocComment(),
                \sprintf(
                    'Method "%s::%s()" is inherited from the EntitySearchResult chain and vanishes with the v6.8.0 hierarchy change. Declare it on ProductListingResult (kept or deprecated) or deprecate it at its declaring class.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName()
                )
            );
        }
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function createSearchResult(?ProductCollection $entities = null): EntitySearchResult
    {
        $entities ??= new ProductCollection();

        return new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
