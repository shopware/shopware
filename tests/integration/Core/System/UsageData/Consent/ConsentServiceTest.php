<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Consent;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testStoresAndReportsRequestedConsentState(): void
    {
        $consentStateReported = false;
        /** @var MockHttpClient $client */
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        $client->setResponseFactory(function (string $method, string $url) use (&$consentStateReported): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            if (\str_ends_with($url, '/v1/consent')) {
                $body = json_encode(['success' => true]);
                static::assertIsString($body);
                $consentStateReported = true;

                return new MockResponse($body);
            }

            return new MockResponse();
        });

        $this->getContainer()->get(ConsentService::class)
            ->requestConsent();

        $consentState = $this->getContainer()->get(SystemConfigService::class)
            ->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE);

        static::assertSame(ConsentState::REQUESTED->value, $consentState);
        static::assertTrue($consentStateReported);
    }
}
