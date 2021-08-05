<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

/**
 * @group store-api
 */
class SitemapRouteTest extends TestCase
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
    }

    public function testEmpty(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/sitemap',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(0, $response);
    }

    public function testSitemapListsEntries(): void
    {
        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create('', $this->ids->get('sales-channel'));

        $fs = $this->getContainer()->get('shopware.filesystem.sitemap');
        $fs->write('sitemap/salesChannel-' . $context->getSalesChannel()->getId() . '-' . $context->getSalesChannel()->getLanguageId() . '/test.xml', 'some content');

        $this->browser
            ->request(
                'POST',
                '/store-api/sitemap',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $response);
        static::assertSame('sitemap', $response[0]['apiAlias']);
        static::assertArrayHasKey('filename', $response[0]);
        static::assertArrayHasKey('created', $response[0]);
        static::assertNotEmpty($response[0]['filename']);
        static::assertNotEmpty($response[0]['created']);
    }
}
