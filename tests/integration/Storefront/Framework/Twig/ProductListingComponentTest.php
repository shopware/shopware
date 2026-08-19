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
#[Package('framework')]
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
     * The listing renders products only. Filters are a separate Sw:Filter:Panel element a layout places
     * where it wants them, so the listing can sit in one grid column with the panel in another without
     * rendering a second panel of its own.
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

    private function listing(): ProductListingResult
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setTranslated(['name' => 'Shopware AG']);

        $aggregations = new AggregationResultCollection([
            new EntityResult('manufacturer', new ProductManufacturerCollection([$manufacturer])),
        ]);

        $result = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            0,
            new ProductCollection(),
            $aggregations,
            new Criteria(),
            Context::createDefaultContext()
        );

        return ProductListingResult::fromSearchResult($result, new ProductSortingCollection());
    }
}