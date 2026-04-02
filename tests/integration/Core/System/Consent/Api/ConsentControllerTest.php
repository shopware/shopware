<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Consent\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ConsentControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testFetchConsentsRequiresAuthentication(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('GET', '/api/consents');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptConsentRequiresAuthentication(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/consents/accept', content: '{ "consent": "test-api-consent" }');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptConsentRequiresConsentParameter(): void
    {
        $browser = $this->getBrowser(true);
        $browser->request('POST', '/api/consents/accept', content: '{}');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptConsentRejectsExplicitRevisionForNonRevisionedConsent(): void
    {
        $browser = $this->getBrowser(true);
        $browser->jsonRequest('POST', '/api/consents/accept', [
            'consent' => 'backend_data',
            'revision' => '1.0.0',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());
    }

    public function testRevokeConsentRequiresAuthentication(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/consents/revoke', content: '{ "consent": "test-api-consent" }');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testRevokeConsentRequiresConsentParameter(): void
    {
        $browser = $this->getBrowser(true);
        $browser->request('POST', '/api/consents/revoke', content: '{}');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }
}
