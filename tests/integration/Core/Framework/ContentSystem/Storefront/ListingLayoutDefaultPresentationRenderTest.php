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
 * Pins the other leg of the two ternaries {@see ListingLayoutQueryParameterRenderTest} reads its `listingLayout`
 * value from: no `listingLayout` query parameter at all. `Sw/Product/Listing.html.twig` falls back to
 * `'default'` and the per-viewport column map, so a request that never supplies the
 * parameter must render `is--layout-default` cards and the multi-viewport column set, not the horizontal ones.
 *
 * This case cannot live in {@see ListingLayoutQueryParameterRenderTest}: `TwigAppVariable::getRequest()`
 * memoizes the first `app.request` read per container (see that class's own docblock), so a second render in
 * the same class would be asserted against the first request's query string. `setUpBeforeClass()` therefore
 * boots a fresh kernel here too, whose container carries its own, unarmed memo.
 *
 * `strict_variables` is false, so a template member that stops resolving renders empty and the route still
 * answers 200; a status assertion proves nothing on its own. The assertions therefore address concrete rendered
 * nodes, the same way the sibling class does.
 *
 * @internal
 */
#[Package('framework')]
class ListingLayoutDefaultPresentationRenderTest extends TestCase
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

    private static bool $kernelBooted = false;

    protected function setUp(): void
    {
        if (!self::$kernelBooted) {
            KernelLifecycleManager::bootKernel();
            self::$kernelBooted = true;
        }

        parent::setUp();

        $this->ids = new IdsCollection();
        $this->createCategoryWithProducts();
        $this->persistLayout();
    }

    #[TestDox('renders the default card presentation and the multi-viewport column set when listingLayout is absent')]
    public function testAbsentListingLayoutParameterRendersTheDefaultPresentation(): void
    {
        $html = $this->render();

        static::assertSame(
            ['is--layout-default', 'is--layout-default'],
            $this->cardLayoutClasses($html),
            $html
        );
        static::assertSame(
            ['xs' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 4],
            $this->gridColumnCounts($html),
            $html
        );
    }

    private function render(): string
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), []);

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
     * @return array<string, int>
     */
    private function gridColumnCounts(string $html): array
    {
        $grids = $this->query($html, self::GRID_XPATH);
        static::assertCount(1, $grids, $html);

        $grid = $grids->item(0);
        static::assertInstanceOf(\DOMElement::class, $grid);

        $style = $grid->getAttribute('style');
        $counts = [];
        foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $breakpoint) {
            static::assertSame(1, preg_match('/--sw-grid-columns-' . $breakpoint . ': ([^;]+);/', $style, $matches));
            $counts[$breakpoint] = substr_count($matches[1], 'minmax(0, 1fr)');
        }

        return $counts;
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
        $this->persistContentLayout($this->ids->create('layout'), 'listing-layout-default-presentation', '1.0.0', 'category', [[
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
            'name' => 'Listing layout default category',
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
                'name' => 'Listing layout default product ' . $index,
                'active' => true,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $this->ids->create('tax-' . $index), 'name' => 'listing-layout-default', 'taxRate' => 19],
                'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $index), 'name' => 'listing-layout-default'],
                'visibilities' => $visibilities,
            ];
        }

        return $products;
    }
}
