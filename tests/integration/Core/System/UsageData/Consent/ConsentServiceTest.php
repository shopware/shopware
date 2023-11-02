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

/**
 * @internal
 */
#[Package('merchant-services')]
class ConsentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

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

        $integrationId = $this->getContainer()->get(SystemConfigService::class)
            ->getString(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION_ID);
        static::assertNotEmpty($integrationId);

        $integration = $this->getContainer()->get('integration.repository')
            ->search(new Criteria([$integrationId]), Context::createDefaultContext())
            ->first();
        static::assertInstanceOf(IntegrationEntity::class, $integration);
    }

    public function testDeletesIntegrationWhenConsentIsRevoked(): void
    {
        $this->getContainer()->get(ConsentService::class)
            ->acceptConsent();

        $integrationId = $this->getContainer()->get(SystemConfigService::class)
            ->getString(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION_ID);
        static::assertNotEmpty($integrationId);

        $this->getContainer()->get(ConsentService::class)
            ->revokeConsent();

        $integration = $this->getContainer()->get('integration.repository')
            ->search(new Criteria([$integrationId]), Context::createDefaultContext())
            ->first();
        static::assertNull($integration);
    }
}
