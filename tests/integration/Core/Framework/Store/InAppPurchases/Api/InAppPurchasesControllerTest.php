<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\InAppPurchases\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchasesControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    public function testActiveInAppPurchasesWithNoPurchases(): void
    {
        InAppPurchase::registerPurchases();

        $ids = new TestDataCollection();
        $integrationId = $ids->create('integration');
        $client = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $client->request('GET', '/api/store/active-in-app-purchases');

        $response = $client->getResponse();
        $content = $response->getContent();

        static::assertNotFalse($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['inAppPurchases' => []], $content);
    }

    public function testActiveInAppPurchasesWithPurchases(): void
    {
        $uuid = Uuid::randomHex();
        InAppPurchase::registerPurchases(['purchase1' => $uuid, 'purchase2' => $uuid]);

        $ids = new TestDataCollection(['integration' => $uuid]);
        $integrationId = $ids->create('integration');
        $client = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $client->request('GET', '/api/store/active-in-app-purchases');

        $response = $client->getResponse();
        $content = $response->getContent();

        static::assertNotFalse($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['inAppPurchases' => ['purchase1', 'purchase2']], $content);
    }

    public function testCheckInAppPurchaseActiveWithoutParameter(): void
    {
        $this->getBrowser()->request('POST', '/api/store/check-in-app-purchase-active');
        $response = $this->getBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $this->getBrowser()->request(
            'POST',
            '/api/store/check-in-app-purchase-active',
            [
                'identifier' => '',
            ]
        );
        $response = $this->getBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode());
    }

    public function testCheckInAppPurchaseActiveWithParameterAndNoPurchases(): void
    {
        InAppPurchase::registerPurchases();
        $this->getBrowser()->request(
            'POST',
            '/api/store/check-in-app-purchase-active',
            [
                'identifier' => 'purchase1',
            ]
        );
        $response = $this->getBrowser()->getResponse();
        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['isActive' => false], $content);
    }

    public function testCheckInAppPurchaseActiveWithParameterAndPurchases(): void
    {
        InAppPurchase::registerPurchases(['purchase1' => 'extension-1', 'purchase2' => 'extension-2']);

        $this->getBrowser()->request(
            'POST',
            '/api/store/check-in-app-purchase-active',
            [
                'identifier' => 'purchase1',
            ]
        );
        $response = $this->getBrowser()->getResponse();
        $content = $this->getBrowser()->getResponse()->getContent();

        static::assertNotFalse($content);
        $content = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['isActive' => true], $content);
    }
}
