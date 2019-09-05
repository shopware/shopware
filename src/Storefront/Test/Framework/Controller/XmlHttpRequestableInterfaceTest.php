<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;

class XmlHttpRequestableInterfaceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testPageLoads(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);
        $client->request('GET', getenv('APP_URL'));

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testAccessDeniedForXmlHttpRequest(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', getenv('APP_URL'));

        static::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoads(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->request('GET', getenv('APP_URL') . '/widgets/checkout/info');

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoadsForXmlHttpRequest(): void
    {
        $client = $this->createSalesChannelBrowser(null, true);

        $client->xmlHttpRequest('GET', getenv('APP_URL') . '/widgets/checkout/info');

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
