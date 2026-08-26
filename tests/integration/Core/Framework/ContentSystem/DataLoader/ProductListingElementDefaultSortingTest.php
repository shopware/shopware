<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingElementLoader;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * Three sources compete for a listing's sorting: `core.listing.defaultSorting`, the element's own
 * `defaultSorting`, and the visitor's `order` parameter. Name and price sort in opposite directions here,
 * so the winner shows in the returned product order rather than only in the reported sorting key.
 *
 * @internal
 */
#[Package('framework')]
class ProductListingElementDefaultSortingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createCategoryWithProducts();

        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    #[TestDox('applies the sales channel default sorting when the element declares none')]
    public function testTheSalesChannelDefaultSortingIsApplied(): void
    {
        $this->setSalesChannelDefaultSorting('price-asc');

        $result = $this->load($this->listingElement());

        static::assertSame('price-asc', $result->getSorting());
        static::assertSame(['cheap', 'medium', 'expensive'], $this->productNames($result->getEntities()));
    }

    #[TestDox('the element default sorting wins over the sales channel setting')]
    public function testTheElementDefaultSortingWinsOverTheSalesChannelSetting(): void
    {
        $this->setSalesChannelDefaultSorting('price-asc');

        $element = $this->listingElement(['defaultSorting' => $this->sortingId('name-asc')]);

        $result = $this->load($element);

        static::assertSame('name-asc', $result->getSorting());
        static::assertSame(['cheap', 'expensive', 'medium'], $this->productNames($result->getEntities()));
    }

    #[TestDox('a sorting chosen by the visitor wins over both defaults')]
    public function testTheRequestedOrderWinsOverBothDefaults(): void
    {
        $this->setSalesChannelDefaultSorting('price-asc');

        $element = $this->listingElement(['defaultSorting' => $this->sortingId('name-asc')]);

        $request = new Request();
        $request->query->set('order', 'price-desc');

        $result = $this->load($element, $request);

        static::assertSame('price-desc', $result->getSorting());
        static::assertSame(['expensive', 'medium', 'cheap'], $this->productNames($result->getEntities()));
    }

    /**
     * The element schema types defaultSorting as a string, so a malformed value reaches the DAL, where the
     * uuid conversion throws and takes the page render with it.
     */
    #[TestDox('a defaultSorting that is not a uuid falls back to the sales channel default instead of throwing')]
    public function testAMalformedDefaultSortingDoesNotAbortTheListing(): void
    {
        $this->setSalesChannelDefaultSorting('price-asc');

        $result = $this->load($this->listingElement(['defaultSorting' => 'name-asc']));

        static::assertSame('price-asc', $result->getSorting());
        static::assertSame(['cheap', 'medium', 'expensive'], $this->productNames($result->getEntities()));
    }

    #[TestDox('an order left in the request bag by an earlier listing run is not a visitor choice')]
    public function testAnOrderInTheRequestBagDoesNotOverrideTheElementDefault(): void
    {
        $this->setSalesChannelDefaultSorting('price-asc');

        $element = $this->listingElement(['defaultSorting' => $this->sortingId('name-asc')]);

        // The classic navigation page runs first and leaves the channel default in the bag. Treating that as
        // a visitor choice would disable every element's own default sorting.
        $request = new Request();
        $request->request->set('order', 'price-asc');

        $result = $this->load($element, $request);

        static::assertSame('name-asc', $result->getSorting());
        static::assertSame(['cheap', 'expensive', 'medium'], $this->productNames($result->getEntities()));
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function listingElement(array $properties = []): ContentElement
    {
        $builder = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $this->ids->get('category'));

        foreach ($properties as $property => $value) {
            $builder->withProperty($property, $value);
        }

        return $builder->build();
    }

    private function load(ContentElement $element, ?Request $request = null): ProductListingResult
    {
        $result = static::getContainer()->get(ProductListingElementLoader::class)
            ->load($element, $this->context, $request ?? new Request());

        static::assertNotNull($result, 'The loader found no listing for the fixture category.');

        return $result;
    }

    private function setSalesChannelDefaultSorting(string $sortingKey): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.listing.defaultSorting',
            $this->sortingId($sortingKey),
            TestDefaults::SALES_CHANNEL
        );
    }

    /**
     * The administration stores the sorting's id, not its key, so the fixtures have to resolve it.
     */
    private function sortingId(string $sortingKey): string
    {
        /** @var EntityRepository<ProductSortingCollection> $repository */
        $repository = static::getContainer()->get('product_sorting.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', $sortingKey));

        $sorting = $repository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($sorting, \sprintf('The default product sorting "%s" is not installed.', $sortingKey));

        return $sorting->getId();
    }

    /**
     * @return list<string>
     */
    private function productNames(ProductCollection $products): array
    {
        return array_values($products->map(static fn (ProductEntity $product): string => (string) $product->getName()));
    }

    private function createCategoryWithProducts(): void
    {
        $taxId = Uuid::randomHex();

        // Names and prices run in opposite directions, so name-asc and price-asc produce different orders.
        $products = [
            ['name' => 'cheap', 'price' => 10],
            ['name' => 'expensive', 'price' => 30],
            ['name' => 'medium', 'price' => 20],
        ];

        $payload = [];
        foreach ($products as $product) {
            $payload[] = [
                'id' => $this->ids->create($product['name']),
                'productNumber' => $this->ids->get($product['name']),
                'name' => $product['name'],
                'stock' => 10,
                'active' => true,
                'tax' => ['id' => $taxId, 'name' => 'test', 'taxRate' => 19],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => $product['price'], 'net' => $product['price'], 'linked' => false],
                ],
                'categories' => [
                    ['id' => $this->ids->create('category'), 'name' => 'listing category'],
                ],
                'visibilities' => [
                    ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        static::getContainer()->get('product.repository')->create($payload, Context::createDefaultContext());
    }
}
