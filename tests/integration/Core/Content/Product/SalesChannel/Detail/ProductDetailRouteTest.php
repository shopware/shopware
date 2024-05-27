<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute
 *
 * @group store-api
 */
class ProductDetailRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->createData();
    }

    public function testLoadProduct(): void
    {
        $this->browser->request('POST', $this->getUrl($this->ids->get('product')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);
    }

    public function testLoadProductVariantShowBestVariant(): void
    {
        $this->createVariantProducts(['displayParent' => true]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);

        $product = $response['product'];
        static::assertArrayHasKey('productNumber', $product);
        static::assertSame('variant-2', $product['productNumber']);
    }

    public function testLoadProductVariantShowSelectedSingleVariant(): void
    {
        $this->createVariantProducts([
            'mainVariantId' => $this->ids->get('variant-3'),
            'displayParent' => false,
        ]);

        $this->browser->request('POST', $this->getUrl($this->ids->get('variants')));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('product_detail', $response['apiAlias']);
        static::assertArrayHasKey('product', $response);

        $product = $response['product'];
        static::assertArrayHasKey('productNumber', $product);
        static::assertSame('variant-3', $product['productNumber']);
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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), print_r($response, true));

        $expected = (string) file_get_contents(__DIR__ . '/_fixtures/recursion_encoding_with_layout_result.json');

        $expected = json_decode($expected, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertArray($expected, $response);
    }

    /**
     * @param array<string, string> $expected
     * @param array<string, string> $actual
     */
    private function assertArray(array $expected, array $actual, string $pointer = ''): void
    {
        foreach ($expected as $key => $value) {
            $current = \implode('.', \array_filter([$pointer, (string) $key]));

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

    /**
     * @param array<mixed> $variantListingConfig
     */
    private function createVariantProducts(array $variantListingConfig): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'variants'))
                ->price(10)
                ->media('m1', 1)
                ->visibility($this->ids->get('sales-channel'))
                ->closeout(true)
                ->stock(10)
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-1'))
                        ->price(5)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(0)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-2'))
                        ->price(15)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(10)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-3'))
                        ->price(40)
                        ->visibility($this->ids->get('sales-channel'))
                        ->closeout(true)
                        ->stock(10)
                        ->build()
                )
                ->variantListingConfig($variantListingConfig)
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());
    }

    private function getUrl(string $id): string
    {
        return '/store-api/product/' . $id;
    }
}
