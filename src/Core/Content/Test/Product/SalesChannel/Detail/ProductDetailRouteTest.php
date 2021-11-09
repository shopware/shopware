<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Detail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group store-api
 */
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

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testLoadProduct(): void
    {
        $this->browser->request('POST', $this->getUrl($this->ids->get('product')));

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('product')),
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
            $this->getUrl($this->ids->get('product')),
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

    public function testRecursionEncodingWithLayout(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl($this->ids->get('with-layout')),
            [
                'associations' => [
                    'media' => [
                        'sort' => [['field' => 'position']],
                    ],
                    'manufacturer' => [],
                    'crossSellings' => [],
                    'productReviews' => [],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));

        $expected = file_get_contents(__DIR__ . '/_fixtures/recursion_encoding_with_layout_result.json');

        $expected = json_decode($expected, true);

        $this->assertArray($expected, $response);
    }

    private function assertArray(array $expected, array $actual, string $pointer = ''): void
    {
        foreach ($expected as $key => $value) {
            $current = empty($pointer) ? $pointer . '.' . $key : (string) $key;

            static::assertArrayHasKey($key, $actual, sprintf('Missing key %s', $current));

            if (\is_array($value)) {
                static::assertIsArray($actual[$key], sprintf('Field %s is not an array', $current));

                $this->assertArray($value, $actual[$key], $current);

                continue;
            }

            static::assertEquals($value, $actual[$key], sprintf('Value for key %s not matching', $current));
        }
    }

    private function createData(): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'product'))
                ->price(15)
                ->manufacturer('m1')
                ->visibility($this->ids->get('sales-channel'))
                ->build(),

            // regression test for: NEXT-17603
            (new ProductBuilder($this->ids, 'with-layout'))
                ->price(100)
                ->media('m1', 1)
                ->media('m2', 2)
                ->media('m3', 3)
                ->review('Test', 'test')
                ->manufacturer('m1')
                ->crossSelling('selling', 'stream-1')
                ->visibility($this->ids->get('sales-channel'))
                ->layout('l1')
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());
    }

    private function getUrl(string $id)
    {
        return '/store-api/product/' . $id;
    }
}
