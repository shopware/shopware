<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class WellKnownControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testRedirectFromPasswordResetRoute(): void
    {
        $response = $this->request('GET', '/.well-known/change-password', []);

        static::assertSame(302, $response->getStatusCode());
        static::assertStringContainsString('account/profile', $response->headers->get('Location'));
        static::assertStringContainsString('profile-password-form', $response->headers->get('Location'));
    }
}
