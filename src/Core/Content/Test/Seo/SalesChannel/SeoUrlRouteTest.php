<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @group store-api
 */
class SeoUrlRouteTest extends TestCase
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
    }

    public function testRequest(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/seo-url',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertSame('seo_url', $response['elements'][0]['apiAlias']);
        static::assertSame('foo', $response['elements'][0]['pathInfo']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/seo-url',
            [
                'includes' => [
                    'seo_url' => [
                        'pathInfo',
                    ],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertSame('seo_url', $response['elements'][0]['apiAlias']);
        static::assertSame('foo', $response['elements'][0]['pathInfo']);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
    }

    public function testFilterMiss(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/seo-url',
            [
                'filter' => [
                    [
                        'type' => 'equals',
                        'field' => 'pathInfo',
                        'value' => 'miss-string',
                    ],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(0, $response['total']);
    }

    public function testFilter(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/seo-url',
            [
                'filter' => [
                    [
                        'type' => 'equals',
                        'field' => 'pathInfo',
                        'value' => 'foo',
                    ],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
    }

    private function createData(): void
    {
        $data = [
            'id' => $this->ids->create('category'),
            'active' => true,
            'name' => 'Test',
        ];

        $this->getContainer()->get('category.repository')
            ->create([$data], $this->ids->context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $data = [
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'routeName' => NavigationPageSeoUrlRoute::ROUTE_NAME,
            'salesChannelId' => $this->ids->get('sales-channel'),
            'pathInfo' => 'foo',
            'seoPathInfo' => 'foo',
            'isCanonical' => true,
            'foreignKey' => $this->ids->get('category'),
        ];

        $this->getContainer()->get('seo_url.repository')
            ->create([$data], $this->ids->context);
    }
}
