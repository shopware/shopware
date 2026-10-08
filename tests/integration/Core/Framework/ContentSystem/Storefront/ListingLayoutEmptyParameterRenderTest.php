<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\ContentSystem\ContentLayoutFixtureBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the falsy-but-present leg of the `listingLayout` ternaries in `Sw/Product/Listing.html.twig`: a
 * `listingLayout` query parameter that is present and empty. Line 69 falls back to `'default'` through
 * `listingLayout ? listingLayout : 'default'`, so an empty value must render `is--layout-default` cards, and
 * line 64 selects the per-viewport column map for anything but `'horizontal'`.
 *
 * `TwigAppVariable::getRequest()` memoizes the first `app.request` read per container, so a render is only
 * asserted against its own query string when the container has not served an earlier request.
 * `setUpBeforeClass()` therefore boots a fresh kernel, whose container carries an unarmed memo, and the class
 * issues exactly one request.
 *
 * `strict_variables` is false, so a template member that stops resolving renders empty and the route still
 * answers 200; a status assertion proves nothing on its own. The assertions therefore address concrete rendered
 * nodes.
 *
 * @internal
 */
#[Package('framework')]
class ListingLayoutEmptyParameterRenderTest extends TestCase
{
    use ContentLayoutFixtureBehaviour;
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const PRODUCT_COUNT = 2;

    /**
     * The card roots, addressed by whole class token so the nested `sw-product-card__*` wrappers are excluded.
     */
    private const CARD_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " sw-product-card ")]';

    private const GRID_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " sw-product-listing__grid ")]/div[contains(concat(" ", normalize-space(@class), " "), " sw-grid-container__inner ")]';

    private IdsCollection $ids;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        KernelLifecycleManager::bootKernel();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createCategoryWithProducts();
        $this->persistLayout();
    }

    #[TestDox('renders the default card presentation and the multi-viewport column set when listingLayout is present but empty')]
    public function testEmptyListingLayoutParameterRendersTheDefaultPresentation(): void
    {
        $html = $this->render();

        static::assertSame(
            ['is--layout-default', 'is--layout-default'],
            $this->cardLayoutClasses($html),
            $html
        );
        static::assertSame(
            ['columns-1', 'columns-lg-4', 'columns-md-3', 'columns-sm-2', 'columns-xl-4'],
            $this->gridColumnClasses($html),
            $html
        );
    }

    private function render(): string
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), ['listingLayout' => '']);

        $html = (string) $response->getContent();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        return $html;
    }

    /**
     * The presentation class of every rendered card, in document order.
     *
     * @return list<string>
     */
    private function cardLayoutClasses(string $html): array
    {
        $cards = $this->query($html, self::CARD_XPATH);
        static::assertCount(self::PRODUCT_COUNT, $cards, $html);

        $classes = [];

        foreach ($cards as $card) {
            static::assertInstanceOf(\DOMElement::class, $card);

            $classes[] = array_values(array_filter(
                $this->classTokens($card),
                static fn (string $token): bool => str_starts_with($token, 'is--layout-')
            ));
        }

        return array_merge([], ...$classes);
    }

    /**
     * The column classes of the one grid container, sorted so the assertion does not pin the CVA emission order.
     *
     * @return list<string>
     */
    private function gridColumnClasses(string $html): array
    {
        $grids = $this->query($html, self::GRID_XPATH);
        static::assertCount(1, $grids, $html);

        $grid = $grids->item(0);
        static::assertInstanceOf(\DOMElement::class, $grid);

        $columns = array_values(array_filter(
            $this->classTokens($grid),
            static fn (string $token): bool => str_starts_with($token, 'columns-')
        ));

        sort($columns);

        return $columns;
    }

    /**
     * @return \DOMNodeList<\DOMNameSpaceNode|\DOMNode>
     */
    private function query(string $html, string $expression): \DOMNodeList
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        static::assertTrue($loaded);

        $nodes = (new \DOMXPath($document))->query($expression);
        static::assertInstanceOf(\DOMNodeList::class, $nodes);

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function classTokens(\DOMElement $element): array
    {
        $tokens = preg_split('/\s+/', trim($element->getAttribute('class')), -1, \PREG_SPLIT_NO_EMPTY);
        static::assertIsArray($tokens);

        return $tokens;
    }

    private function persistLayout(): void
    {
        $this->persistContentLayout($this->ids->create('layout'), 'listing-layout-empty-parameter', '1.0.0', 'category', [[
            'id' => $this->ids->create('listing'),
            'component' => 'Sw:Product:Listing',
            'properties' => [
                'navigationId' => $this->ids->get('category'),
            ],
            'dataRequirements' => [
                'listing' => ['source' => 'product_listing', 'config' => ['property' => 'navigationId']],
            ],
        ]]);

        $this->assignLayoutToCategory(
            $this->ids->create('assignment'),
            $this->ids->get('category'),
            $this->ids->get('layout'),
        );
    }

    private function createCategoryWithProducts(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Listing layout empty parameter category',
            'active' => true,
            'products' => $this->products(),
        ]], Context::createDefaultContext());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        $salesChannelIds = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        $visibilities = array_map(
            static fn (string $salesChannelId): array => [
                'salesChannelId' => $salesChannelId,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ],
            $salesChannelIds
        );

        $products = [];

        for ($index = 0; $index < self::PRODUCT_COUNT; ++$index) {
            $id = $this->ids->create('product-' . $index);

            $products[] = [
                'id' => $id,
                'productNumber' => $id,
                'name' => 'Listing layout empty parameter product ' . $index,
                'active' => true,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $this->ids->create('tax-' . $index), 'name' => 'listing-layout-empty-parameter', 'taxRate' => 19],
                'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $index), 'name' => 'listing-layout-empty-parameter'],
                'visibilities' => $visibilities,
            ];
        }

        return $products;
    }
}
