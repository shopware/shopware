<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Consent;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('merchant-services')]
class ConsentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        /** @var MockHttpClient $client */
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        $client->setResponseFactory(function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });
    }

    public function testStoresRequestedConsentState(): void
    {
        $this->getContainer()->get(ConsentService::class)
            ->requestConsent();

        $consentState = $this->getContainer()->get(SystemConfigService::class)
            ->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE);

        static::assertSame(ConsentState::REQUESTED->value, $consentState);
    }

    public function testCreatesIntegrationWhenConsentIsAccepted(): void
    {
        $this->getContainer()->get(ConsentService::class)
            ->acceptConsent();

        $integration = $this->getContainer()->get(SystemConfigService::class)
            ->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);
        static::assertIsArray($integration);
        static::assertArrayHasKey('integrationId', $integration);

        $integration = $this->getContainer()->get('integration.repository')
            ->search(new Criteria([$integration['integrationId']]), Context::createDefaultContext())
            ->first();
        static::assertInstanceOf(IntegrationEntity::class, $integration);
    }

    public function testDeletesIntegrationWhenConsentIsRevoked(): void
    {
        $this->getContainer()->get(ConsentService::class)
            ->acceptConsent();

        $integration = $this->getContainer()->get(SystemConfigService::class)
            ->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);
        static::assertIsArray($integration);
        static::assertArrayHasKey('integrationId', $integration);

        $this->getContainer()->get(ConsentService::class)
            ->revokeConsent();

        $integration = $this->getContainer()->get('integration.repository')
            ->search(new Criteria([$integration['integrationId']]), Context::createDefaultContext())
            ->first();
        static::assertNull($integration);
    }
}
