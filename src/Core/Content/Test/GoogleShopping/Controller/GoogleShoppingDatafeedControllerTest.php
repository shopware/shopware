<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Exception\DatafeedNotFoundException;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingDatafeedIntegration;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use function Flag\skipTestNext6050;

class GoogleShoppingDatafeedControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use GoogleShoppingIntegration;
    use GoogleShoppingDatafeedIntegration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var KernelBrowser
     */
    private $client;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->context = Context::createDefaultContext();
        $this->getMockGoogleClient();
        $this->client = $this->getBrowser();
    }

    public function testGetDatafeedNotFound(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], Uuid::randomHex());

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/datafeed'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString((new DatafeedNotFoundException())->getErrorCode(), $response->getContent());
    }

    public function testGetDatafeedSuccess(): void
    {
        $dataFeedId = '4536789';
        $merchantId = Uuid::randomHex();

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $id = $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->updatetDatafeedtoMerchantAccount($dataFeedId, $id);

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/datafeed'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArrayHasKey('id', $response['data']);
    }

    public function testGetDatafeedStatusNotFound(): void
    {
        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], Uuid::randomHex());

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/datafeed/status'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertStringContainsString((new DatafeedNotFoundException())->getErrorCode(), $response->getContent());
    }

    public function testGetDatafeedStatusSuccess(): void
    {
        $dataFeedId = '4536789';
        $merchantId = Uuid::randomHex();

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex());

        $id = $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->updatetDatafeedtoMerchantAccount($dataFeedId, $id);

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $googleAccounts['googleAccount']['salesChannelId'] . '/google-shopping/datafeed/status'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArrayHasKey('processingStatus', $response['data']);
    }

    public function testSyncProduct(): void
    {
        $datafeedId = '4536789';
        $merchantId = Uuid::randomHex();

        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $this->createProductExportEntity($salesChannelId);

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $id = $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->updatetDatafeedtoMerchantAccount($datafeedId, $id);

        $this->client->request(
            'POST',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/datafeed/sync'
        );

        $response = $this->client->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $response = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArrayHasKey('id', $response['data']);
    }
}
