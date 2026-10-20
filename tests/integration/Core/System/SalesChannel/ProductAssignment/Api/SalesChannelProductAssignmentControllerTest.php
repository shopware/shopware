<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\ProductAssignment\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelException;
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
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        $this->getProductRepository()->create([
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
        static::assertSame(['assigned' => 3], $this->decodeResponse());

        $visibilities = $this->visibilitiesOfSalesChannel();

        static::assertSame([
            'STREAM-already-visible' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'STREAM-match' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'already-visible' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'explicit' => ProductVisibilityDefinition::VISIBILITY_ALL,
            'in-category' => ProductVisibilityDefinition::VISIBILITY_ALL,
        ], $visibilities);
    }

    public function testItUsesTheRequestedVisibility(): void
    {
        $this->getBrowser()->jsonRequest('POST', $this->url(), [
            'productIds' => [$this->ids->get('explicit')],
            'visibility' => ProductVisibilityDefinition::VISIBILITY_LINK,
        ]);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame(['assigned' => 1], $this->decodeResponse());
        static::assertSame(ProductVisibilityDefinition::VISIBILITY_LINK, $this->visibilitiesOfSalesChannel()['explicit']);
    }

    public function testItAssignsNothingForAnEmptyRequest(): void
    {
        $this->getBrowser()->jsonRequest('POST', $this->url(), []);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame(['assigned' => 0], $this->decodeResponse());
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
    }

    public function testItRejectsInvalidIdsAndVisibility(): void
    {
        $this->getBrowser()->jsonRequest('POST', $this->url(), [
            'productIds' => ['not-a-uuid'],
            'visibility' => 15,
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());

        $pointers = array_column($this->decodeResponse()['errors'], 'source');
        $pointers = array_column($pointers, 'pointer');

        static::assertContains('/productIds', $pointers);
        static::assertContains('/visibility', $pointers);
    }

    public function testItReportsAnUnknownSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', $this->url($salesChannelId), [
            'productIds' => [$this->ids->get('explicit')],
        ]);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame(SalesChannelException::SALES_CHANNEL_DOES_NOT_EXISTS_EXCEPTION, $this->decodeResponse()['errors'][0]['code']);
    }

    public function testItRequiresTheProductVisibilityCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['product:read', 'sales_channel:update']);
        $browser->jsonRequest('POST', $this->url(), [
            'productIds' => [$this->ids->get('explicit')],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testItRequiresTheProductReadPrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['product_visibility:create']);
        $browser->jsonRequest('POST', $this->url(), [
            'productIds' => [$this->ids->get('explicit')],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testItRequiresTheProductStreamReadPrivilegeForProductGroups(): void
    {
        $browser = $this->getBrowser(true, [], ['product_visibility:create', 'product:read', 'category:read']);
        $browser->jsonRequest('POST', $this->url(), [
            'productStreamIds' => [$this->ids->get('stream')],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $this->decodeResponseOf($browser)['errors'][0]['code']);
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
    }

    public function testItRequiresTheCategoryReadPrivilegeForCategories(): void
    {
        $browser = $this->getBrowser(true, [], ['product_visibility:create', 'product:read', 'product_stream:read']);
        $browser->jsonRequest('POST', $this->url(), [
            'categoryIds' => [$this->ids->get('assignment-category')],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $this->decodeResponseOf($browser)['errors'][0]['code']);
        static::assertSame(['STREAM-already-visible', 'already-visible'], array_keys($this->visibilitiesOfSalesChannel()));
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
        $criteria->addAssociation('product');

        $visibilities = [];
        foreach ($this->getVisibilityRepository()->search($criteria, $this->context)->getEntities() as $visibility) {
            $product = $visibility->getProduct();
            static::assertNotNull($product);
            $visibilities[(string) $product->getProductNumber()] = $visibility->getVisibility();
        }
        ksort($visibilities);

        return $visibilities;
    }

    /**
     * @return EntityRepository<ProductCollection>
     */
    private function getProductRepository(): EntityRepository
    {
        return static::getContainer()->get('product.repository');
    }

    /**
     * @return EntityRepository<ProductVisibilityCollection>
     */
    private function getVisibilityRepository(): EntityRepository
    {
        return static::getContainer()->get('product_visibility.repository');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        return $this->decodeResponseOf($this->getBrowser());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponseOf(TestBrowser $browser): array
    {
        $content = $browser->getResponse()->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
