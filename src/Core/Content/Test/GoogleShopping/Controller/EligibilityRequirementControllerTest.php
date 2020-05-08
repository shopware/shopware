<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use function Flag\skipTestNext6050;

class EligibilityRequirementControllerTest extends TestCase
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

    public function testGetEligibilityRequirementListWithSalesChannelIsNotConnectedProductExportFailure(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();
        $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/eligibility-requirements'
        );

        $response = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertEquals('CONTENT__GOOGLE_SHOPPING_SALES_CHANNEL_IS_NOT_LINKED_TO_PRODUCT_EXPORT', $response['errors'][0]['code'] ?? null);
    }

    public function testGetEligibilityRequirementListSuccess(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();
        $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);
        $storefrontSalesChannelId = $this->createStorefrontSalesChannel();
        $this->createProductExport($salesChannelId, $storefrontSalesChannelId);

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/eligibility-requirements'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);

        static::assertArrayHasKey('data', $response);

        static::assertArrayHasKey('shoppingAdsPolicies', $response['data']);
        static::assertTrue($response['data']['shoppingAdsPolicies']);

        static::assertArrayHasKey('contactPage', $response['data']);
        static::assertTrue($response['data']['contactPage']);

        static::assertArrayHasKey('secureCheckoutProcess', $response['data']);
        static::assertTrue($response['data']['secureCheckoutProcess']);

        static::assertArrayHasKey('revocationPage', $response['data']);
        static::assertTrue($response['data']['revocationPage']);

        static::assertArrayHasKey('shippingPaymentInfoPage', $response['data']);
        static::assertTrue($response['data']['shippingPaymentInfoPage']);

        static::assertArrayHasKey('completeCheckoutProcess', $response['data']);
        static::assertTrue($response['data']['completeCheckoutProcess']);
    }

    public function testGetEligibilityRequirementListWithStorefrontSalesChannelOnMaintenanceModeSuccess(): void
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();
        $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);
        $storefrontSalesChannelId = $this->createStorefrontSalesChannel(true);
        $this->createProductExport($salesChannelId, $storefrontSalesChannelId);

        $this->client->request(
            'GET',
            '/api/v1/_action/sales-channel/' . $salesChannelId . '/google-shopping/eligibility-requirements'
        );

        static::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $response);

        static::assertArrayHasKey('shoppingAdsPolicies', $response['data']);
        static::assertTrue($response['data']['shoppingAdsPolicies']);

        static::assertArrayHasKey('contactPage', $response['data']);
        static::assertTrue($response['data']['contactPage']);

        static::assertArrayHasKey('secureCheckoutProcess', $response['data']);
        static::assertTrue($response['data']['secureCheckoutProcess']);

        static::assertArrayHasKey('revocationPage', $response['data']);
        static::assertTrue($response['data']['revocationPage']);

        static::assertArrayHasKey('shippingPaymentInfoPage', $response['data']);
        static::assertTrue($response['data']['shippingPaymentInfoPage']);

        static::assertArrayHasKey('completeCheckoutProcess', $response['data']);
        static::assertFalse($response['data']['completeCheckoutProcess']);
    }
}
