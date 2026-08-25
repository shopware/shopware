<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class FilterPanelComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The canonicity test only validates bindings that exist, so dropping `resolvedBy` would pass it while
     * leaving the element unwired.
     */
    public function testElementTypeBindsTheAggregationsLoader(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $specification = $registry->all()['core:Sw:Filter:Panel'] ?? null;
        static::assertInstanceOf(BindingSpecification::class, $specification);
        static::assertSame('Sw:Filter:Panel', $specification->type());

        $binding = $specification->resolves()['filterAggregations'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $binding);
        static::assertSame('product_listing_aggregations', $binding->loader);

        // The loader reads `navigationId` by default. Naming that key in the binding would be read as
        // a resolvedBy storage key and rejected for colliding with the declared property of the same
        // name, so the default has to stay implicit.
        static::assertArrayNotHasKey('property', $binding->config);
    }

    /**
     * One layout serves every category page, so a stored id would be right on one and wrong on all the others.
     */
    public function testNavigationIdFollowsThePageInsteadOfBeingConfigured(): void
    {
        static::assertSame('{{categoryId}}', $this->properties()['navigationId']->toSchema()['default']);
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
     * The value names the arrangement while the CSS class keeps naming the Bootstrap mechanism it turns
     * on, so the mapping between the two is worth pinning.
     */
    public function testDisplayTypeReachesTheFilterItems(): void
    {
        $aggregations = $this->manufacturerAggregation('Shopware AG');

        $inline = $this->render(['filterAggregations' => $aggregations, 'displayType' => 'inline']);
        $stacked = $this->render(['filterAggregations' => $aggregations, 'displayType' => 'stacked']);

        static::assertStringContainsString('is--dropdown', $inline);
        static::assertStringContainsString('data-bs-toggle="dropdown"', $inline);
        static::assertStringNotContainsString('data-bs-target="#filter-item-', $inline);

        static::assertStringContainsString('is--collapse', $stacked);
        static::assertStringContainsString('data-bs-toggle="collapse"', $stacked);
        static::assertStringContainsString('data-bs-target="#filter-item-', $stacked);
    }

    public function testRendersFiltersFromTheAggregations(): void
    {
        $html = $this->render(['filterAggregations' => $this->manufacturerAggregation('Shopware AG')]);

        static::assertStringContainsString('data-component="Sw:Filter:Panel"', $html);
        static::assertStringContainsString('Shopware AG', $html);
    }

    /**
     * The panel owns the summary, so a panel in a sidebar column takes its chips along instead of leaving them
     * stranded next to the product grid.
     */
    public function testRendersTheActiveFiltersSummary(): void
    {
        $html = $this->render(['filterAggregations' => $this->manufacturerAggregation('Shopware AG')]);

        static::assertStringContainsString('data-component="Sw:Filter:ActiveFilters"', $html);
    }

    /**
     * The summary has to be a descendant, not a second root: Sw:Grid:Container lays its children out as
     * grid items, so a second root element would claim the next cell and push the neighbouring element
     * onto a new row.
     */
    public function testRendersTheSummaryInsideItsSingleRootElement(): void
    {
        $html = $this->render(['filterAggregations' => $this->manufacturerAggregation('Shopware AG')]);

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
        static::assertCount(1, $summaries);
    }

    /**
     * Degrading to a filterless panel instead of failing the render matches Sw:Media:Image.
     */
    public function testRendersWithoutFiltersWhenTheListingIsMissing(): void
    {
        $html = $this->render([]);

        static::assertStringContainsString('data-component="Sw:Filter:Panel"', $html);
        static::assertStringNotContainsString('data-component="Sw:Filter:Item"', $html);
    }

    /**
     * The free shipping filter is a panel item like the others, so it takes the same wrapper and collapse
     * behaviour instead of sitting loose among them.
     */
    public function testRendersTheFreeShippingFilterAsAFilterItem(): void
    {
        $html = $this->render(['filterAggregations' => $this->shippingFreeAggregation()]);

        static::assertStringContainsString('data-component="Sw:Filter:Type:BooleanFilter"', $html);
        static::assertSame(1, substr_count($html, 'data-component="Sw:Filter:Item"'));

        // Only the wrapper may carry the item class; a second one would be counted twice by the panel's
        // collapse, which selects `.sw-filter-item` to decide what `visibleFilterCount` hides.
        static::assertStringNotContainsString('sw-boolean-filter sw-filter sw-filter-item', $html);
    }

    /**
     * @return array<string, PropertySpecification>
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

    private function manufacturerAggregation(string $name): AggregationResultCollection
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setTranslated(['name' => $name]);

        return new AggregationResultCollection([
            new EntityResult('manufacturer', new ProductManufacturerCollection([$manufacturer])),
        ]);
    }

    private function shippingFreeAggregation(): AggregationResultCollection
    {
        return new AggregationResultCollection([
            new MaxResult('shipping-free', 1),
        ]);
    }
}
