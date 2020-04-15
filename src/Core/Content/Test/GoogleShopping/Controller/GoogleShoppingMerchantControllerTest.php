<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use function Flag\skipTestNext6050;

class GoogleShoppingMerchantControllerTest extends TestCase
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

    public function testGetMerchantInfoFails(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/merchant/info'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_CONNECTED_ACCOUNT_NOT_FOUND', $response->getContent());

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/info'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_CONNECTED_MERCHANT_ACCOUNT_NOT_FOUND', $response->getContent());
    }

    public function testGetMerchantInfoSuccess(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], Uuid::randomHex());

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/info'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testGetMerchantListSuccess(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/list'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testAssignMerchantAccountFails(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $merchantId = Uuid::randomHex();

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/assign'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response->getContent());

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/assign',
            ['merchantId' => $merchantId]
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_ALREADY_CONNECTED_MERCHANT_ACCOUNT', $response->getContent());
    }

    public function testUnAssignMerchantAccountFails(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/unassign'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString('CONTENT__GOOGLE_SHOPPING_CONNECTED_MERCHANT_ACCOUNT_NOT_FOUND', $response->getContent());
    }

    public function testAssignMerchantAccountSuccess(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $merchantId = Uuid::randomHex();

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/assign',
            ['merchantId' => $merchantId]
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testUnAssignMerchantAccountSuccess(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $merchantId = Uuid::randomHex();

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/merchant/unassign'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }
}
