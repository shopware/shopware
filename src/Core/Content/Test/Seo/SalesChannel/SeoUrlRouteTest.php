<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class SeoUrlRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
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

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
            ->create([$data], Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $data = [
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
            'salesChannelId' => $this->ids->get('sales-channel'),
            'pathInfo' => 'foo',
            'seoPathInfo' => 'foo',
            'isCanonical' => true,
            'foreignKey' => $this->ids->get('category'),
        ];

        $this->getContainer()->get('seo_url.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
