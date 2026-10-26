<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\ProductAssignment\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentController;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelProductAssignmentControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'explicit'))->price(10)->build(),
            (new ProductBuilder($this->ids, 'already-visible'))->price(10)->visibility()->build(),
            (new ProductBuilder($this->ids, 'in-category'))->price(10)->category('assignment-category')->build(),
            (new ProductBuilder($this->ids, 'STREAM-match'))->price(10)->build(),
            (new ProductBuilder($this->ids, 'STREAM-already-visible'))->price(10)->visibility()->build(),
            (new ProductBuilder($this->ids, 'untouched'))->price(10)->build(),
        ], $this->context);

        static::getContainer()->get('product_stream.repository')->create([[
            'id' => $this->ids->create('stream'),
            'name' => 'Stream products',
            'filters' => [['type' => 'contains', 'field' => 'productNumber', 'value' => 'STREAM-']],
        ]], $this->context);
    }

    public function testItAssignsProductsFromIdsCategoriesAndProductGroupsOnce(): void
    {
        $this->getBrowser()->jsonRequest('POST', $this->url(), [
            'productIds' => [$this->ids->get('explicit'), $this->ids->get('already-visible')],
            'productStreamIds' => [$this->ids->get('stream')],
            'categoryIds' => [$this->ids->get('assignment-category')],
        ]);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame(['assigned' => 3, 'complete' => true], $this->decodeResponse($this->getBrowser()));

        static::assertSame([
            'STREAM-already-visible' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'STREAM-match' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'already-visible' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'explicit' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'in-category' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ], $this->visibilitiesOfSalesChannel());
    }

    public function testItRejectsInvalidIdsAndVisibility(): void
    {
        $this->getBrowser()->jsonRequest('POST', $this->url(), [
            'productIds' => ['not-a-uuid'],
            'visibility' => 15,
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());

        $pointers = array_column($this->decodeResponse($this->getBrowser())['errors'], 'source');
        $pointers = array_column($pointers, 'pointer');

        static::assertContains('/productIds', $pointers);
        static::assertContains('/visibility', $pointers);
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
    }

    public function testItRejectsMoreProductGroupsThanTheLimitAllows(): void
    {
        $streamIds = array_map(
            static fn (): string => Uuid::randomHex(),
            range(1, SalesChannelProductAssignmentController::MAX_PRODUCT_STREAM_IDS + 1),
        );

        $this->getBrowser()->jsonRequest('POST', $this->url(), ['productStreamIds' => $streamIds]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());

        $pointers = array_column($this->decodeResponse($this->getBrowser())['errors'], 'source');
        $pointers = array_column($pointers, 'pointer');

        static::assertSame(['/productStreamIds'], $pointers);
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
    }

    /**
     * @param list<string> $privileges
     */
    #[DataProvider('missingPrivilegeProvider')]
    public function testItRejectsCallersWithoutTheRequiredPrivilege(array $privileges, string $source, string $idKey): void
    {
        $browser = $this->getBrowser(true, [], $privileges);
        $browser->jsonRequest('POST', $this->url(), [$source => [$this->ids->get($idKey)]]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $this->decodeResponse($browser)['errors'][0]['code']);
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
    }

    /**
     * @return iterable<string, array{list<string>, string, string}>
     */
    public static function missingPrivilegeProvider(): iterable
    {
        yield 'without product_visibility:create nothing is assigned' => [['product:read', 'sales_channel:update'], 'productIds', 'explicit'];
        yield 'without product:read even explicit ids are rejected' => [['product_visibility:create'], 'productIds', 'explicit'];
        yield 'without product_stream:read a product group is not resolved' => [['product_visibility:create', 'product:read', 'category:read'], 'productStreamIds', 'stream'];
    }

    private function url(string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        return \sprintf('/api/_action/sales-channel/%s/product-assignment', $salesChannelId);
    }

    /**
     * @return array<string, int> visibility per product number, sorted by product number
     */
    private function visibilitiesOfSalesChannel(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL));
        $criteria->addFilter(new EqualsAnyFilter('productId', array_values($this->ids->all())));

        /** @var EntityRepository<ProductVisibilityCollection> $visibilityRepository */
        $visibilityRepository = static::getContainer()->get('product_visibility.repository');

        $visibilities = [];
        foreach ($visibilityRepository->search($criteria, $this->context)->getEntities() as $visibility) {
            $visibilities[(string) $this->ids->getKey($visibility->getProductId())] = $visibility->getVisibility();
        }
        ksort($visibilities);

        return $visibilities;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(TestBrowser $browser): array
    {
        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
