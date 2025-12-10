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
        $browser->request('POST', '/api/consents/test-api-consent/accept');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testRevokeConsentRequiresAuthentication(): void
    {
        $browser = $this->getBrowser(false);
        $browser->request('POST', '/api/consents/test-api-consent/revoke');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }
}
