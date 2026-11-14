<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin\Indexer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;

/**
 * @internal
 */
#[Package('inventory')]
class ProductAdminSearchIndexerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private string $visibleSalesChannelId;

    private string $otherSalesChannelId;

    private AdminElasticsearchHelper $adminEsHelper;

    private bool $adminEsEnabled;

    protected function setUp(): void
    {
        $this->adminEsHelper = static::getContainer()->get(AdminElasticsearchHelper::class);
        $this->adminEsEnabled = $this->adminEsHelper->isEnabled();
        $this->adminEsHelper->setEnabled(true);

        $this->ids = new IdsCollection();
        $this->visibleSalesChannelId = $this->createSalesChannelWithDomain();
        $this->otherSalesChannelId = $this->createSalesChannelWithDomain();

        $product = (new ProductBuilder($this->ids, 'product'))
            ->price(10)
            ->visibility($this->visibleSalesChannelId)
            ->media('product-media')
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
    }

    protected function tearDown(): void
    {
        $this->adminEsHelper->setEnabled($this->adminEsEnabled);
    }

    public function testDeletedVisibilityMarksItsProduct(): void
    {
        $visibilityId = $this->visibilityId($this->visibleSalesChannelId);

        $updatedIds = $this->updatedIdsOf(fn (Context $context) => static::getContainer()->get('product_visibility.repository')->delete([['id' => $visibilityId]], $context));

        static::assertSame([$this->ids->get('product')], $updatedIds);
    }

    public function testVisibilityMovedToAnotherSalesChannelMarksItsProduct(): void
    {
        $visibilityId = $this->visibilityId($this->visibleSalesChannelId);

        $updatedIds = $this->updatedIdsOf(fn (Context $context) => static::getContainer()->get('product_visibility.repository')->update([
            ['id' => $visibilityId, 'salesChannelId' => $this->otherSalesChannelId],
        ], $context));

        static::assertSame([$this->ids->get('product')], $updatedIds);
    }

    public function testChangedVisibilityValueDoesNotMarkItsProduct(): void
    {
        $visibilityId = $this->visibilityId($this->visibleSalesChannelId);

        $updatedIds = $this->updatedIdsOf(fn (Context $context) => static::getContainer()->get('product_visibility.repository')->update([
            ['id' => $visibilityId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_SEARCH],
        ], $context));

        static::assertSame([], $updatedIds);
    }

    public function testDeletedProductMediaMarksItsProduct(): void
    {
        $updatedIds = $this->updatedIdsOf(fn (Context $context) => static::getContainer()->get('product_media.repository')->delete([
            ['id' => $this->ids->get('product-media')],
        ], $context));

        static::assertSame([$this->ids->get('product')], $updatedIds);
    }

    /**
     * @param \Closure(Context): EntityWrittenContainerEvent $write
     *
     * @return array<string>
     */
    private function updatedIdsOf(\Closure $write): array
    {
        return Feature::withFeatureEnabled('ENABLE_OPENSEARCH_FOR_ADMIN_API', static function () use ($write): array {
            $event = $write(Context::createDefaultContext());

            return static::getContainer()->get(ProductAdminSearchIndexer::class)->getUpdatedIds($event);
        });
    }

    private function visibilityId(string $salesChannelId): string
    {
        $visibilityId = static::getContainer()->get('product_visibility.repository')->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('productId', $this->ids->get('product')))
                ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId)),
            Context::createDefaultContext()
        )->firstId();

        static::assertIsString($visibilityId);

        return $visibilityId;
    }

    private function createSalesChannelWithDomain(): string
    {
        return $this->createSalesChannel([
            'id' => Uuid::randomHex(),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://admin-es-' . Uuid::randomHex() . '.example',
            ]],
        ])['id'];
    }
}
