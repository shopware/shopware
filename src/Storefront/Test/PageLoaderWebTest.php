<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PageLoaderWebTest extends WebTestCase
{
    use StorefrontFunctionalTestBehaviour;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->checkoutContext = Generator::createCheckoutContext();
    }

    public function testIndexPageload(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
