<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Storefront;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the Experience Studio layout resolution order on the real Storefront product and category routes:
 * an explicit Experience Studio assignment, then the Experience Studio default of the entity type, then the
 * untouched legacy CMS resolution. Every scenario keeps a legacy CMS page assigned to the entity, so a
 * rendered Experience Studio layout proves it overrode the CMS assignment rather than filled an empty slot.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutAssignmentResolutionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const LEGACY_CMS_MARKER = 'legacy-cms-layout-marker';

    private IdsCollection $ids;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $this->createLegacyCmsPage('legacy-category-page', 'product_list');
        $this->createLegacyCmsPage('legacy-product-page', 'product_detail');
        $this->createCategory();
        $this->createProduct();
        $this->createContentLayout('category-layout', 'category');
        $this->createContentLayout('product-layout', 'product');
    }

    protected function tearDown(): void
    {
        // The system config cache outlives the rolled back transaction, so the defaults are unset explicitly.
        $this->systemConfigService->delete(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);
        $this->systemConfigService->delete(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);

        parent::tearDown();
    }

    #[TestDox('renders the legacy CMS category layout when Experience Studio has no assignment')]
    public function testCategoryFallsBackToLegacyCmsLayout(): void
    {
        $this->assertRendersLegacyCmsLayout($this->requestCategory());
    }

    #[TestDox('renders the explicitly assigned Experience Studio layout instead of the legacy CMS category layout')]
    public function testExplicitCategoryAssignmentOverridesLegacyCmsLayout(): void
    {
        $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('renders the default Experience Studio category layout instead of the legacy CMS category layout')]
    public function testDefaultCategoryLayoutOverridesLegacyCmsLayout(): void
    {
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('category-layout'));

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('renders the explicitly assigned category layout instead of the default Experience Studio category layout')]
    public function testExplicitCategoryAssignmentOverridesDefaultCategoryLayout(): void
    {
        $this->createContentLayout('default-category-layout', 'category');
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('default-category-layout'));
        $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');

        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');
    }

    #[TestDox('renders the legacy CMS category layout again once the Experience Studio assignment is removed')]
    public function testRemovedCategoryAssignmentRestoresLegacyCmsLayout(): void
    {
        $assignmentId = $this->assignLayout('category_content_layout.repository', 'categoryId', 'category', 'category-layout');
        $this->assertRendersContentLayout($this->requestCategory(), 'category-layout');

        $this->repository('category_content_layout.repository')->delete([['id' => $assignmentId]], Context::createDefaultContext());

        $this->assertRendersLegacyCmsLayout($this->requestCategory());
    }

    #[TestDox('does not resolve the default Experience Studio product layout for a category')]
    public function testDefaultProductLayoutDoesNotApplyToCategories(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->assertRendersLegacyCmsLayout($this->requestCategory());
    }

    #[TestDox('renders the legacy CMS product layout when Experience Studio has no assignment')]
    public function testProductFallsBackToLegacyCmsLayout(): void
    {
        $this->assertRendersLegacyCmsLayout($this->requestProduct());
    }

    #[TestDox('renders the explicitly assigned Experience Studio layout instead of the legacy CMS product layout')]
    public function testExplicitProductAssignmentOverridesLegacyCmsLayout(): void
    {
        $this->assignLayout('product_content_layout.repository', 'productId', 'product', 'product-layout');

        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');
    }

    #[TestDox('renders the default Experience Studio product layout instead of the legacy CMS product layout')]
    public function testDefaultProductLayoutOverridesLegacyCmsLayout(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');
    }

    #[TestDox('renders the legacy CMS product layout again once the Experience Studio assignment is removed')]
    public function testRemovedProductAssignmentRestoresLegacyCmsLayout(): void
    {
        $assignmentId = $this->assignLayout('product_content_layout.repository', 'productId', 'product', 'product-layout');
        $this->assertRendersContentLayout($this->requestProduct(), 'product-layout');

        $this->repository('product_content_layout.repository')->delete([['id' => $assignmentId]], Context::createDefaultContext());

        $this->assertRendersLegacyCmsLayout($this->requestProduct());
    }

    #[TestDox('does not resolve the default Experience Studio category layout for a product')]
    public function testDefaultCategoryLayoutDoesNotApplyToProducts(): void
    {
        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('category-layout'));

        $this->assertRendersLegacyCmsLayout($this->requestProduct());
    }

    #[TestDox('rejects a product layout as the default category layout')]
    public function testRejectsProductLayoutAsDefaultCategoryLayout(): void
    {
        $this->expectExceptionObject(ContentSystemException::rootSourceAssignmentMismatch('product', 'category'));

        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));
    }

    #[TestDox('blocks deleting the layout that is the default product layout')]
    public function testBlocksDeletingTheDefaultLayout(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->expectExceptionObject(ContentSystemException::defaultContentLayoutDeletion([$this->ids->get('product-layout')]));

        $this->repository('content_layout.repository')->delete([['id' => $this->ids->get('product-layout')]], Context::createDefaultContext());
    }

    private function assertRendersContentLayout(string $html, string $layoutKey): void
    {
        static::assertStringContainsString('data-element-id="' . $this->ids->get($layoutKey . '-root') . '"', $html);
        static::assertStringNotContainsString(self::LEGACY_CMS_MARKER, $html);
    }

    private function assertRendersLegacyCmsLayout(string $html): void
    {
        static::assertStringContainsString(self::LEGACY_CMS_MARKER, $html);
        static::assertStringNotContainsString('data-element-id="', $html);
    }

    private function requestCategory(): string
    {
        return $this->requestPage('navigation/' . $this->ids->get('category'));
    }

    private function requestProduct(): string
    {
        return $this->requestPage('detail/' . $this->ids->get('product'));
    }

    private function requestPage(string $path): string
    {
        $response = $this->request('GET', $path, []);

        // The technical route redirects to the canonical SEO url, which is served by the same controller action.
        if ($response->isRedirection()) {
            $location = (string) $response->headers->get('Location');
            $response = $this->request('GET', ltrim((string) parse_url($location, \PHP_URL_PATH), '/'), []);
        }

        $html = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $html);

        return $html;
    }

    private function assignLayout(string $repositoryId, string $entityIdField, string $entityKey, string $layoutKey): string
    {
        $assignmentId = Uuid::randomHex();

        $this->repository($repositoryId)->create([[
            'id' => $assignmentId,
            $entityIdField => $this->ids->get($entityKey),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get($layoutKey),
        ]], Context::createDefaultContext());

        return $assignmentId;
    }

    private function createContentLayout(string $layoutKey, string $rootSource): void
    {
        $this->repository('content_layout.repository')->create([[
            'id' => $this->ids->create($layoutKey),
            'name' => $layoutKey,
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [[
                'id' => $this->ids->create($layoutKey . '-root'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'slots' => [],
            ]],
        ]], Context::createDefaultContext());
    }

    private function createLegacyCmsPage(string $pageKey, string $type): void
    {
        $this->repository('cms_page.repository')->create([[
            'id' => $this->ids->create($pageKey),
            'name' => $pageKey,
            'type' => $type,
            'sections' => [[
                'position' => 0,
                'type' => 'default',
                'blocks' => [[
                    'position' => 0,
                    'type' => 'text',
                    'sectionPosition' => 'main',
                    'slots' => [[
                        'type' => 'text',
                        'slot' => 'content',
                        'config' => ['content' => ['source' => 'static', 'value' => self::LEGACY_CMS_MARKER]],
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());
    }

    private function createCategory(): void
    {
        $navigationCategoryId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->getSalesChannelId())]
        );

        $this->repository('category.repository')->create([[
            'id' => $this->ids->create('category'),
            'parentId' => $navigationCategoryId,
            'name' => 'Layout assignment category',
            'active' => true,
            'cmsPageId' => $this->ids->get('legacy-category-page'),
        ]], Context::createDefaultContext());
    }

    private function createProduct(): void
    {
        $product = (new ProductBuilder($this->ids, 'product'))
            ->price(10)
            ->visibility($this->getSalesChannelId())
            ->build();

        $product['cmsPageId'] = $this->ids->get('legacy-product-page');

        $this->repository('product.repository')->create([$product], Context::createDefaultContext());
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
