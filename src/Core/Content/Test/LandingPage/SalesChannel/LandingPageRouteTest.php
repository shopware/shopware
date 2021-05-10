<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\LandingPage\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\Exception\LandingPageNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser as KernelBrowserAlias;

/**
 * @group store-api
 */
class LandingPageRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var KernelBrowserAlias
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

    public function testWithDifferentSalesChannel(): void
    {
        $this->createSalesChannel([
            'id' => $this->ids->create('other-sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://testing',
                ],
            ],
        ]);

        $this->createData([
            'id' => $this->ids->create('new-landing-page'),
            'salesChannels' => [
                [
                    'id' => $this->ids->get('other-sales-channel'),
                ],
            ],
        ]);

        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('new-landing-page')
        );

        $this->assertError($this->ids->get('new-landing-page'));
    }

    public function testCmsPageResolved(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($this->ids->get('landing-page'), $response['id']);
        static::assertIsArray($response['cmsPage']);

        static::assertEquals($this->ids->get('cms-page'), $response['cmsPage']['id']);
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertEquals('product-listing', $block['type']);

        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertEquals('product-listing', $slot['type']);

        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];

        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
    }

    public function testIncludesConsidered(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/landing-page/' . $this->ids->get('landing-page'),
            [
                'includes' => [
                    'product_manufacturer' => ['id', 'name', 'options'],
                    'product' => ['id', 'name', 'manufacturer', 'tax'],
                    'product_listing' => ['aggregations', 'elements'],
                    'tax' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];

        static::assertArrayNotHasKey('sortings', $listing);
        static::assertArrayNotHasKey('page', $listing);
        static::assertArrayNotHasKey('limit', $listing);

        static::assertArrayHasKey('manufacturer', $listing['aggregations']);
        $manufacturers = $listing['aggregations']['manufacturer'];

        foreach ($manufacturers['entities'] as $manufacturer) {
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($manufacturer));
        }

        $products = $listing['elements'];
        foreach ($products as $product) {
            static::assertEquals(['name', 'tax', 'manufacturer', 'id', 'apiAlias'], array_keys($product));
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($product['tax']));
        }
    }

    private function assertError(string $landingPageId): void
    {
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $error = new LandingPageNotFoundException($landingPageId);
        $expectedError = [
            'status' => (string) $error->getStatusCode(),
            'message' => $error->getMessage(),
        ];

        static::assertSame($expectedError['status'], $response['errors'][0]['status']);
        static::assertSame($expectedError['message'], $response['errors'][0]['detail']);
    }

    private function createData(array $override = []): void
    {
        $data = [
            'id' => $this->ids->create('landing-page'),
            'name' => 'Test',
            'url' => 'myUrl',
            'active' => true,
            'salesChannels' => [
                [
                    'id' => $this->ids->get('sales-channel'),
                ],
            ],
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $data = array_merge($data, $override);

        $this->getContainer()->get('landing_page.repository')
            ->create([$data], $this->ids->context);
    }
}
