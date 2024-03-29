<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testConsentIsNotGivenIfConsentStateIsNotPresent(): void
    {
        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/usage-data/consent');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);

        $consent = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($consent);
        static::assertArrayHasKey('isConsentGiven', $consent);
        static::assertFalse($consent['isConsentGiven']);

        $consentState = $this->getContainer()->get(SystemConfigService::class)->getString(
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE
        );
        static::assertSame(ConsentState::REQUESTED->value, $consentState);
    }

    public function testConsentIsGivenIfConsentStateIsAccepted(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE,
            ConsentState::ACCEPTED->value
        );

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/usage-data/consent');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);

        $consent = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($consent);
        static::assertArrayHasKey('isConsentGiven', $consent);
        static::assertTrue($consent['isConsentGiven']);

        $consentState = $this->getContainer()->get(SystemConfigService::class)->getString(
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE
        );
        static::assertSame(ConsentState::ACCEPTED->value, $consentState);
    }

    public function testConsentStateIsStoredInSystemConfigWhenAccepted(): void
    {
        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/usage-data/accept-consent');

        $consentState = $this->getContainer()->get(SystemConfigService::class)->getString(
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE
        );
        static::assertSame(ConsentState::ACCEPTED->value, $consentState);
    }

    public function testConsentStateIsStoredInSystemConfigWhenRevoked(): void
    {
        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/usage-data/revoke-consent');

        $consentState = $this->getContainer()->get(SystemConfigService::class)->getString(
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE
        );
        static::assertSame(ConsentState::REVOKED->value, $consentState);
    }
}
