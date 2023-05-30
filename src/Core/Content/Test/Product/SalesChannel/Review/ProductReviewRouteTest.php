<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\FloatComparator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class ProductReviewRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->setVisibilities();

        $this->createReviews();
    }

    public function testLoad(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('total', $response);
        static::assertEquals(5, $response['total']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'product_review' => ['title', 'content', 'points'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $first = array_shift($response['elements']);
        $properties = array_keys($first);

        $expected = ['title', 'content', 'points', 'apiAlias'];
        sort($properties);
        sort($expected);

        static::assertEquals($expected, $properties);
    }

    public function testExtendCriteria(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'product_review' => ['title', 'content', 'points'],
                ],
                'aggregations' => [
                    ['name' => 'average', 'type' => 'avg', 'field' => 'points'],
                    ['name' => 'max', 'type' => 'max', 'field' => 'points'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('aggregations', $response);

        static::assertArrayHasKey('max', $response['aggregations']);
        static::assertArrayHasKey('average', $response['aggregations']);

        static::assertTrue(FloatComparator::equals(3.4, $response['aggregations']['average']['avg']));
        static::assertEquals(5, $response['aggregations']['max']['max']);
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
            ->update($update, Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $reviews = [];
        for ($i = 1; $i <= 5; ++$i) {
            $reviews[] = [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => $this->ids->get('sales-channel'),
                'productId' => $this->ids->get('product'),
                'title' => 'Test',
                'content' => 'test',
                'points' => min(5, $i + $i / 5),
                'status' => true,
            ];
        }

        $this->getContainer()->get('product_review.repository')
            ->create($reviews, Context::createDefaultContext());
    }

    private function getUrl()
    {
        return '/store-api/product/' . $this->ids->get('product') . '/reviews';
    }
}
