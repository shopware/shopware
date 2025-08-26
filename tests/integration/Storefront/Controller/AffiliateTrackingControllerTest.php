<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class AffiliateTrackingControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testStoreAffiliateCodeInSession(): void
    {
        $affiliateCode = 'test-affiliate-123';
        $payload = [OrderService::AFFILIATE_CODE_KEY => $affiliateCode];

        $response = $this->request('POST', '/affiliate-tracking', $payload);
        static::assertSame(200, $response->getStatusCode());

        $session = $this->getSession();
        static::assertSame($affiliateCode, $session->get(OrderService::AFFILIATE_CODE_KEY));
    }

    public function testStoreCampaignCodeInSession(): void
    {
        $campaignCode = 'test-campaign-456';
        $payload = [OrderService::CAMPAIGN_CODE_KEY => $campaignCode];

        $response = $this->request('POST', '/affiliate-tracking', $payload);

        static::assertSame(200, $response->getStatusCode());

        $session = $this->getSession();
        static::assertSame($campaignCode, $session->get(OrderService::CAMPAIGN_CODE_KEY));
    }

    public function testStoreBothAffiliateAndCampaignCodes(): void
    {
        $affiliateCode = 'test-affiliate-789';
        $campaignCode = 'test-campaign-101112';
        $payload = [
            OrderService::AFFILIATE_CODE_KEY => $affiliateCode,
            OrderService::CAMPAIGN_CODE_KEY => $campaignCode,
        ];

        $response = $this->request('POST', '/affiliate-tracking', $payload);

        static::assertSame(200, $response->getStatusCode());

        $session = $this->getSession();
        static::assertSame($affiliateCode, $session->get(OrderService::AFFILIATE_CODE_KEY));
        static::assertSame($campaignCode, $session->get(OrderService::CAMPAIGN_CODE_KEY));
    }

    public function testEmptyPayloadDoesNotModifySession(): void
    {
        $session = $this->getSession();
        $session->set(OrderService::AFFILIATE_CODE_KEY, 'existing-affiliate');
        $session->set(OrderService::CAMPAIGN_CODE_KEY, 'existing-campaign');

        $response = $this->request('POST', '/affiliate-tracking', []);
        static::assertSame(200, $response->getStatusCode());

        static::assertSame('existing-affiliate', $session->get(OrderService::AFFILIATE_CODE_KEY));
        static::assertSame('existing-campaign', $session->get(OrderService::CAMPAIGN_CODE_KEY));
    }

    public function testOnlyAffiliateCodeOverwritesExistingValues(): void
    {
        $session = $this->getSession();
        $session->set(OrderService::AFFILIATE_CODE_KEY, 'old-affiliate');
        $session->set(OrderService::CAMPAIGN_CODE_KEY, 'old-campaign');

        $newAffiliateCode = 'new-affiliate-code';
        $payload = [OrderService::AFFILIATE_CODE_KEY => $newAffiliateCode];

        $response = $this->request('POST', '/affiliate-tracking', $payload);
        static::assertSame(200, $response->getStatusCode());

        static::assertSame($newAffiliateCode, $session->get(OrderService::AFFILIATE_CODE_KEY));
        static::assertSame('old-campaign', $session->get(OrderService::CAMPAIGN_CODE_KEY));
    }
}
