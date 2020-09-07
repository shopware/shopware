<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Detail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;

class ProductDetailRouteTest extends TestCase
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

    public function testLoadProduct(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'product' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);

        $product = $response['product'];
        $properties = array_keys($product);

        $expected = ['id', 'name', 'apiAlias'];
        sort($expected);
        sort($properties);

        static::assertEquals($expected, $properties);
    }

    public function testExtendCriteria(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'product' => ['id', 'name', 'manufacturer'],
                ],
                'associations' => [
                    'manufacturer' => [],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);
        static::assertArrayHasKey('manufacturer', $response['product']);
        static::assertNotEmpty($response['product']['manufacturer']);
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
        return '/store-api/v' . PlatformRequest::API_VERSION . '/product/' . $this->ids->get('product');
    }
}
