<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Pins the default-layout gate through the real system config and DAL write paths: a default must name an existing
 * layout of the key's entity type, and a layout cannot be deleted while it is a default.
 *
 * @internal
 */
#[Package('framework')]
class ContentLayoutDefaultValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ids = new IdsCollection();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->createLayout('product-layout', 'product');
    }

    protected function tearDown(): void
    {
        // The system config cache outlives the rolled back transaction, so the defaults are unset explicitly.
        $this->systemConfigService->delete(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);
        $this->systemConfigService->delete(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT);

        parent::tearDown();
    }

    #[TestDox('rejects a product layout as the default category layout')]
    public function testRejectsProductLayoutAsDefaultCategoryLayout(): void
    {
        $this->expectExceptionObject(ContentSystemException::rootSourceAssignmentMismatch('product', 'category'));

        $this->systemConfigService->set(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));
    }

    #[TestDox('rejects a default layout id that names no existing layout')]
    public function testRejectsDefaultLayoutThatDoesNotExist(): void
    {
        $this->expectExceptionObject(ContentSystemException::contentLayoutNotFound($this->ids->get('missing-layout')));

        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('missing-layout'));
    }

    #[TestDox('blocks deleting the layout that is the default product layout')]
    public function testBlocksDeletingTheDefaultLayout(): void
    {
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));

        $this->expectExceptionObject(ContentSystemException::defaultContentLayoutDeletion([$this->ids->get('product-layout')]));

        $this->layoutRepository()->delete([['id' => $this->ids->get('product-layout')]], Context::createDefaultContext());
    }

    #[TestDox('allows deleting the layout once it is no longer the default')]
    public function testAllowsDeletingTheLayoutAfterUnsettingTheDefault(): void
    {
        $context = Context::createDefaultContext();

        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('product-layout'));
        $this->systemConfigService->set(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, null);

        $this->layoutRepository()->delete([['id' => $this->ids->get('product-layout')]], $context);

        static::assertSame(0, $this->layoutRepository()->searchIds(new Criteria([$this->ids->get('product-layout')]), $context)->getTotal());
    }

    private function createLayout(string $layoutKey, string $rootSource): void
    {
        $this->layoutRepository()->create([[
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

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function layoutRepository(): EntityRepository
    {
        $repository = static::getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
