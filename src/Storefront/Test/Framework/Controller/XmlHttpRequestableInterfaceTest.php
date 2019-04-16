<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;

class XmlHttpRequestableInterfaceTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    public function testPageLoads()
    {
        $client = $this->createSalesChannelClient(null, true);
        $client->request('GET', getenv('APP_URL'));

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testAccessDeniedForXmlHttpRequest()
    {
        $client = $this->createSalesChannelClient(null, true);

        $client->xmlHttpRequest('GET', getenv('APP_URL'));

        static::assertEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoads()
    {
        $client = $this->createSalesChannelClient(null, true);

        $client->request('GET', getenv('APP_URL') . '/widgets/checkout/info');

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testPageletLoadsForXmlHttpRequest()
    {
        $client = $this->createSalesChannelClient(null, true);

        $client->xmlHttpRequest('GET', getenv('APP_URL') . '/widgets/checkout/info');

        static::assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
