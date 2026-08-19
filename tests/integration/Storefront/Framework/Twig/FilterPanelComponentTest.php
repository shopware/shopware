<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
class FilterPanelComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The canonicity test only validates bindings that exist, so dropping `resolvedBy` would pass it
     * while leaving the element unwired.
     */
    public function testElementTypeBindsTheProductListingLoader(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $specification = $registry->all()['core:Sw:Filter:Panel'] ?? null;
        static::assertInstanceOf(BindingSpecification::class, $specification);
        static::assertSame('Sw:Filter:Panel', $specification->type());

        $binding = $specification->resolves()['listing'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $binding);
        static::assertSame('product_listing', $binding->loader);

        // The loader reads `navigationId` by default. Naming that key in the binding would be read as
        // a resolvedBy storage key and rejected for colliding with the declared property of the same
        // name, so the default has to stay implicit.
        static::assertArrayNotHasKey('property', $binding->config);
    }

    /**
     * The panel loads its own listing so it can be placed beside a product listing rather than inside
     * it, because context never reaches a sibling. A second binding would mean a second way to wire
     * that, and there is no use case asking for one.
     */
    public function testTheTypeShipsNoBindingBeyondTheSynthesizedDefault(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $ownBindings = array_keys(array_filter(
            $registry->all(),
            static fn (BindingSpecification $specification): bool => $specification->type() === 'Sw:Filter:Panel'
        ));

        static::assertSame(['core:Sw:Filter:Panel'], $ownBindings);
    }

    /**
     * One layout serves every category page, so a stored id would be right on one and wrong on all
     * the others.
     */
    public function testNavigationIdFollowsThePageInsteadOfBeingConfigured(): void
    {
        $navigationId = $this->properties()['navigationId']->toSchema();

        static::assertSame('{{categoryId}}', $navigationId['default']);

        // Studio derives a control from the property type, so a primitive is always shown and this
        // field cannot be hidden declaratively yet. The help text is what keeps an editor from
        // "correcting" the placeholder it displays.
        static::assertNotNull($navigationId['adminUI']['helpText'] ?? null);
    }

    /**
     * A required primitive with a default is reported unresolved until a write seeds it, so the
     * placeholder-carrying property must stay optional.
     */
    public function testNavigationIdIsNotRequired(): void
    {
        static::assertFalse($this->properties()['navigationId']->required());
    }

    /**
     * The enum is not cosmetic: Sw:Filter:Item branches on `displayType == 'inline'` and otherwise takes
     * the stacked path, so a third value would emit `data-bs-toggle="collapse"` with no `data-bs-target`
     * and the filter would never open.
     */
    public function testDisplayTypeIsConstrainedToTheTwoSupportedArrangements(): void
    {
        $displayType = $this->properties()['displayType']->toSchema();

        static::assertSame('inline', $displayType['default']);
        static::assertSame([
            'inline',
            'stacked',
        ], $displayType['enum']);
    }

    /**
     * The value names the arrangement while the CSS class keeps naming the Bootstrap mechanism it turns
     * on, so the mapping between the two is worth pinning.
     */
    public function testDisplayTypeReachesTheFilterItems(): void
    {
        $listing = $this->listing(42, $this->manufacturerAggregation('Shopware AG'));

        $inline = $this->render(['listing' => $listing, 'displayType' => 'inline']);
        $stacked = $this->render(['listing' => $listing, 'displayType' => 'stacked']);

        static::assertStringContainsString('is--dropdown', $inline);
        static::assertStringContainsString('data-bs-toggle="dropdown"', $inline);
        static::assertStringNotContainsString('data-bs-target="#filter-item-', $inline);

        static::assertStringContainsString('is--collapse', $stacked);
        static::assertStringContainsString('data-bs-toggle="collapse"', $stacked);
        static::assertStringContainsString('data-bs-target="#filter-item-', $stacked);
    }

    public function testDerivesFiltersAndResultCountFromTheListing(): void
    {
        $html = $this->render(['listing' => $this->listing(42, $this->manufacturerAggregation('Shopware AG'))]);

        static::assertStringContainsString('data-component="Sw:Filter:Panel"', $html);
        static::assertStringContainsString('Shopware AG', $html);
        static::assertStringContainsString('42', $html);
    }

    /**
     * Sw:Product:Listing renders the panel as its slot default and hands over the result it already
     * loaded, so the same component must accept the parts directly.
     */
    public function testExplicitPropsWinOverTheListing(): void
    {
        $html = $this->render([
            'listing' => $this->listing(42, $this->manufacturerAggregation('Shopware AG')),
            'filterAggregations' => $this->manufacturerAggregation('Overridden Manufacturer'),
        ]);

        static::assertStringContainsString('Overridden Manufacturer', $html);
        static::assertStringNotContainsString('Shopware AG', $html);
    }

    /**
     * The panel owns the active-filter summary, so a panel placed in a sidebar column takes its chips
     * along instead of leaving them stranded next to the product grid.
     */
    public function testRendersTheActiveFiltersSummary(): void
    {
        $html = $this->render(['listing' => $this->listing(42, $this->manufacturerAggregation('Shopware AG'))]);

        static::assertStringContainsString('data-component="Sw:Filter:ActiveFilters"', $html);
    }

    /**
     * The summary has to be a descendant, not a second root: Sw:Grid:Container lays its children out as
     * grid items, so a second root element would claim the next cell and push the neighbouring element
     * onto a new row.
     */
    public function testRendersTheSummaryInsideItsSingleRootElement(): void
    {
        $html = $this->render(['listing' => $this->listing(42, $this->manufacturerAggregation('Shopware AG'))]);

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>');
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);
        static::assertInstanceOf(\DOMElement::class, $body);

        $roots = [];
        foreach ($body->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $roots[] = $node;
            }
        }

        static::assertCount(1, $roots);
        static::assertStringContainsString('sw-filter-panel', $roots[0]->getAttribute('class'));

        $summaries = (new \DOMXPath($document))->query('//*[@data-component="Sw:Filter:ActiveFilters"]');
        static::assertInstanceOf(\DOMNodeList::class, $summaries);
        static::assertSame(1, $summaries->count());
    }

    /**
     * A loader that finds no listing yields no data at all. Degrading to a filterless panel instead
     * of failing the render matches Sw:Media:Image and Sw:Product:Listing.
     */
    public function testRendersWithoutFiltersWhenTheListingIsMissing(): void
    {
        $html = $this->render([]);

        static::assertStringContainsString('data-component="Sw:Filter:Panel"', $html);
        static::assertStringNotContainsString('data-component="Sw:Filter:Item"', $html);
    }

    /**
     * @return array<string, \Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification>
     */
    private function properties(): array
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        return $types->get('Sw:Filter:Panel')->properties();
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Sw:Filter:Panel\', props) }}')
            ->render(['props' => $props]);
    }

    private function listing(int $total, ?AggregationResultCollection $aggregations = null): ProductListingResult
    {
        $result = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $total,
            new ProductCollection(),
            $aggregations,
            new Criteria(),
            Context::createDefaultContext()
        );

        // getAvailableSortings() reads an uninitialized typed property unless the collection is passed,
        // and the production listing route always fills it via the sorting processor.
        return ProductListingResult::fromSearchResult($result, new ProductSortingCollection());
    }

    private function manufacturerAggregation(string $name): AggregationResultCollection
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setTranslated(['name' => $name]);

        return new AggregationResultCollection([
            new EntityResult('manufacturer', new ProductManufacturerCollection([$manufacturer])),
        ]);
    }
}