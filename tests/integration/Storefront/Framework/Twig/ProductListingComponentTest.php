<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class ProductListingComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The component reads `app.request` for the layout query parameter, which throws without a request
     * on the stack.
     */
    protected function setUp(): void
    {
        $this->requestStack()->push(new Request());
    }

    protected function tearDown(): void
    {
        $this->requestStack()->pop();
    }

    /**
     * Filters are a separate Sw:Filter:Panel element, so the listing can sit in one grid column with the panel
     * in another without rendering a second panel of its own.
     */
    public function testRendersNoFilterUiOfItsOwn(): void
    {
        $html = $this->render(['listing' => $this->listing()]);

        static::assertStringContainsString('data-component="Sw:Product:Listing"', $html);
        static::assertStringNotContainsString('data-component="Sw:Filter:Panel"', $html);
        static::assertStringNotContainsString('data-component="Sw:Filter:ActiveFilters"', $html);
    }

    /**
     * The type must not advertise slots the template no longer renders, because Studio would offer
     * drop targets that silently swallow whatever is placed into them.
     */
    public function testDeclaresNoFilterSlots(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $slots = array_map(
            static fn (SlotSpecification $slot): string => $slot->name(),
            $types->get('Sw:Product:Listing')->slots()
        );

        static::assertSame([
            'product-grid',
            'pagination',
        ], $slots);
    }

    /**
     * The sorting select and the layout switch belong to the listing, as they did in the CMS listing element,
     * so the result count sits with them rather than in the filter panel.
     */
    public function testRendersTheSortingActionsAndResultCount(): void
    {
        $html = $this->render(['listing' => $this->listing(42)]);

        static::assertStringContainsString('sw-product-listing__actions', $html);
        static::assertStringContainsString('data-component="Sw:Product:LayoutSwitch"', $html);
        static::assertStringContainsString('42', $html);
    }

    public function testHidesTheSortingActionsWhenTurnedOff(): void
    {
        $html = $this->render([
            'listing' => $this->listing(42),
            'showSorting' => false,
            'showLayoutSwitch' => false,
        ]);

        static::assertStringNotContainsString('data-component="Sw:Product:Sorting"', $html);
        static::assertStringNotContainsString('data-component="Sw:Product:LayoutSwitch"', $html);
    }

    private function requestStack(): RequestStack
    {
        $requestStack = static::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);

        return $requestStack;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig
            ->createTemplate('{{ component(\'Sw:Product:Listing\', props) }}')
            ->render(['props' => $props]);
    }

    private function listing(int $total = 0): ProductListingResult
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setTranslated(['name' => 'Shopware AG']);

        $aggregations = new AggregationResultCollection([
            new EntityResult('manufacturer', new ProductManufacturerCollection([$manufacturer])),
        ]);

        $result = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $total,
            new ProductCollection(),
            $aggregations,
            new Criteria(),
            Context::createDefaultContext()
        );

        return ProductListingResult::fromSearchResult($result, new ProductSortingCollection());
    }
}
