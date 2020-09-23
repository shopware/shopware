<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class AclControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetPrivileges(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_3722', $this);
        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/acl/privileges');
        $response = $this->getBrowser()->getResponse();
        $privileges = json_decode($response->getContent(), true);

        static::assertContains('unit:read', $privileges);
        static::assertContains('system:clear:cache', $privileges);
    }

    public function testGetRoutePrivileges(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_3722', $this);
        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/acl/route_privileges');
        $response = $this->getBrowser()->getResponse();
        $privileges = json_decode($response->getContent(), true);

        static::assertNotContains('unit:read', $privileges);
        static::assertContains('system:clear:cache', $privileges);
    }

    public function testGetRoutePrivilegesNoPermission(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_3722', $this);
        try {
            $this->authorizeBrowser($this->getBrowser(), [], []);
            $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/acl/route_privileges');
            $response = $this->getBrowser()->getResponse();

            static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
        } finally {
            $this->resetBrowser();
        }
    }
}
