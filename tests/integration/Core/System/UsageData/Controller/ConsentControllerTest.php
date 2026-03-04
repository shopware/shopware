<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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
    }

    public function testConsentIsGivenIfConsentStateIsAccepted(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('INSERT INTO `consent_state`
            (`id`, `name`, `identifier`, `state`, `actor`, `updated_at`)
            VALUES (:id, "backend_data", "system", "accepted", "admin", NOW())
        ', ['id' => Uuid::randomBytes()]);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/usage-data/consent');

        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);

        $consent = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($consent);
        static::assertArrayHasKey('isConsentGiven', $consent);
        static::assertTrue($consent['isConsentGiven']);
    }

    public function testConsentStateIsStoredWhenAccepted(): void
    {
        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/usage-data/accept-consent');

        $consentState = static::getContainer()->get(Connection::class)->executeQuery('SELECT `state` FROM `consent_state` WHERE `name` = "backend_data"')->fetchOne();
        static::assertSame('accepted', $consentState);
    }

    public function testConsentStateIsStoredInSystemConfigWhenRevoked(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('INSERT INTO `consent_state`
            (`id`, `name`, `identifier`, `state`, `actor`, `updated_at`)
            VALUES (:id, "backend_data", "system", "accepted", "admin", NOW())
        ', ['id' => Uuid::randomBytes()]);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/usage-data/revoke-consent');

        $consentState = static::getContainer()->get(Connection::class)->executeQuery('SELECT `state` FROM `consent_state` WHERE `name` = "backend_data"')->fetchOne();
        static::assertSame('revoked', $consentState);
    }

    public function testConsentStateIsStoredInSystemConfigWhenDeclined(): void
    {
        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/usage-data/revoke-consent');

        $consentState = static::getContainer()->get(Connection::class)->executeQuery('SELECT `state` FROM `consent_state` WHERE `name` = "backend_data"')->fetchOne();
        static::assertSame('declined', $consentState);
    }
}
