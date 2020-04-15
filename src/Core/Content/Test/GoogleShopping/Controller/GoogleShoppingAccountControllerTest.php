<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use function Flag\skipTestNext6050;

class GoogleShoppingAccountControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $client;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->context = Context::createDefaultContext();
        $this->getMockGoogleClient();
        $this->client = $this->getBrowser();
    }

    public function testAccountConnectFails(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/account/connect'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_INVALID_AUTHORIZATION_CODE', $response->getContent());

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/account/connect',
            ['code' => 'GOOGLE.INVALID.CODE']
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_INVALID_AUTHORIZATION_CODE', $response->getContent());

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/account/connect',
            ['code' => 'VALID.AUTHORIZATION.CODE']
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_ALREADY_CONNECTED_ACCOUNT', $response->getContent());
    }

    public function testAccountConnectSuccess(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/account/connect',
            ['code' => 'VALID.AUTHORIZATION.CODE']
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testAccountDisconnectFails(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/account/disconnect'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_CONNECTED_ACCOUNT_NOT_FOUND', $response->getContent());
    }

    public function testAccountDisconnectSuccess(): void
    {
        $googleAccount = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccount['googleAccount']['salesChannelId'] . '/google-shopping/account/disconnect'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
}
