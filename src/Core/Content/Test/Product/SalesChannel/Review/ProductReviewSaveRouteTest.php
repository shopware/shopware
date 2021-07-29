<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Review;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group store-api
 */
class ProductReviewSaveRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->setVisibilities();
    }

    public function testRequiresLogin(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(403, $response->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($response['errors'][0]['code'], 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN');
    }

    public function testCreate(): void
    {
        $this->login();

        $this->assertReviewCount(0);

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();
        $content = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals(204, $response->getStatusCode(), print_r($content, true));

        $this->assertReviewCount(1);
    }

    public function testUpdate(): void
    {
        $this->login();

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        $this->browser->request('POST', $this->getUrl(), [
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();
        $content = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals(204, $response->getStatusCode(), print_r($content, true));

        $this->assertReviewCount(1);

        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);
        $this->assertReviewCount(1);
    }

    public function testValidation(): void
    {
        $this->login();

        $this->browser->request('POST', $this->getUrl());

        $response = $this->browser->getResponse();

        static::assertEquals(400, $response->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($response['errors'][0]['source']['pointer'], '/title');
        static::assertEquals($response['errors'][1]['source']['pointer'], '/content');
    }

    public function testCustomerValidation(): void
    {
        $this->login();

        $this->assertReviewCount(0);

        $id = Uuid::randomHex();

        // Create review
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        // Re-login as another user
        $this->login();

        // Try to use the id from previous review which is not attached to this customer
        $this->browser->request('POST', $this->getUrl(), [
            'id' => $id,
            'title' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna',
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);

        static::assertSame('VIOLATION::ENTITY_DOES_NOT_EXISTS', $content['errors'][0]['code']);
    }

    private function assertReviewCount(int $expected): void
    {
        $count = $this->getContainer()
            ->get(Connection::class)
            ->fetchColumn('SELECT COUNT(*) FROM product_review WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($this->ids->get('product'))]);

        static::assertEquals($expected, $count);
    }

    private function createData(): void
    {
        $product = [
            'id' => $this->ids->create('product'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'productNumber' => $this->ids->get('product'),
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('product'),
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')
            ->update($update, $this->ids->context);
    }

    private function getUrl()
    {
        return '/store-api/product/' . $this->ids->get('product') . '/review';
    }
}
