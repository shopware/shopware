<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the read leg of the card-grid presentation parameter: a persisted `content_layout` carrying a
 * `Sw:Product:Listing` element is rendered through the real `frontend.content.layout` route, and the query
 * parameter that selects the horizontal presentation is `listingLayout`.
 *
 * Both directions are pinned by one request that seeds both keys with conflicting values, because that single
 * observation separates all three possible reads: reading `listingLayout` renders the horizontal presentation,
 * reading the superseded `layout` renders the default one, and reading neither also renders the default one.
 * Asserting the horizontal presentation therefore fails both on a lost read of the new key and on a
 * re-introduced read of the old one.
 *
 * It has to be one request, and that request has to be the first `app.request` read of its container.
 * `Shopware\Storefront\Framework\Twig\TwigAppVariable::getRequest()` memoizes its cloned request in a private
 * property and carries neither a `reset()` nor a `kernel.reset` tag, and the integration harness reuses one
 * non-rebooting kernel for the whole process (`StorefrontControllerTestBehaviour::request()` →
 * `KernelLifecycleManager::createBrowser()` with reboot disabled), so the first render in the process that
 * reads `app.request` fixes it for every later render — neither `services_resetter` nor `clearRequestStack()`
 * clears it. A second request here would be asserted against the first request's query string, and any earlier
 * `app.request` reader in the same process (any test that renders through `base.html.twig`, so most of
 * `tests/integration/Storefront/`) would supply its own query string to this one. `setUpBeforeClass()`
 * therefore boots a fresh kernel, whose container carries a fresh, unarmed decorator.
 *
 * `strict_variables` is false, so a template member that stops resolving renders empty and the route still
 * answers 200; a status assertion proves nothing on its own. The assertions therefore address concrete
 * rendered nodes — the one grid container and the per-product card roots — and read their presentation
 * classes, which are the two consumers of the parameter inside `Sw/Product/Listing.html.twig`. The response
 * body is passed as the assertion message so a blank or restructured render is readable.
 *
 * @internal
 */
#[Package('framework')]
class ListingLayoutQueryParameterRenderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const PRODUCT_COUNT = 2;

    /**
     * The card roots, addressed by whole class token so the nested `sw-product-card__*` wrappers are excluded.
     */
    private const CARD_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " sw-product-card ")]';

    private const GRID_XPATH = '//div[contains(concat(" ", normalize-space(@class), " "), " sw-product-listing__grid ")]';

    private IdsCollection $ids;

    /**
     * A fresh container carries a fresh `TwigAppVariable`, so this class's render is the first `app.request`
     * read of a memo the harness never resets. Before the transaction hook, so no open transaction is dropped.
     */
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

    #[TestDox('reads the card grid presentation from listingLayout and not from the superseded layout key')]
    public function testListingLayoutParameterIsReadAndTheSupersededOneIsNot(): void
    {
        $html = $this->render(['layout' => 'default', 'listingLayout' => 'horizontal']);

        static::assertSame(
            ['is--layout-horizontal', 'is--layout-horizontal'],
            $this->cardLayoutClasses($html),
            $html
        );
        static::assertSame(
            ['columns-1', 'columns-lg-1', 'columns-md-1', 'columns-sm-1', 'columns-xl-1'],
            $this->gridColumnClasses($html),
            $html
        );
    }

    /**
     * @param array<string, string> $query
     */
    private function render(array $query): string
    {
        $response = $this->request('GET', 'content/category/' . $this->ids->get('category'), $query);

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

            foreach ($this->classTokens($card) as $token) {
                if (str_starts_with($token, 'is--layout-')) {
                    $classes[] = $token;
                }
            }
        }

        return $classes;
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
        $context = Context::createDefaultContext();

        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create('layout'),
            'name' => 'listing-layout-parameter',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->create('listing'),
                'component' => 'Sw:Product:Listing',
                'properties' => [
                    'navigationId' => $this->ids->get('category'),
                ],
                'dataRequirements' => [
                    'listing' => ['source' => 'product_listing', 'config' => ['property' => 'navigationId']],
                ],
            ]],
        ]], $context);

        $this->repository('category_content_layout.repository')->create([[
            'id' => $this->ids->create('assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('layout'),
        ]], $context);
    }

    private function createCategoryWithProducts(): void
    {
        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'name' => 'Listing layout category',
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
                'name' => 'Listing layout product ' . $index,
                'active' => true,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['id' => $this->ids->create('tax-' . $index), 'name' => 'listing-layout', 'taxRate' => 19],
                'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $index), 'name' => 'listing-layout'],
                'visibilities' => $visibilities,
            ];
        }

        return $products;
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $serviceId): EntityRepository
    {
        $repository = static::getContainer()->get($serviceId);
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
