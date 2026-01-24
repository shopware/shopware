<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class WellKnownControllerTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use StorefrontControllerTestBehaviour;

    public function testRedirectFromPasswordResetRoute(): void
    {
        $response = $this->request('GET', '/.well-known/change-password', []);

        static::assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location');

        static::assertIsString($location);
        static::assertStringContainsString('account/profile', $location);
        static::assertStringContainsString('profile-password-form', $location);
    }
}
