<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Saas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoService;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoService::class)]
class SsoServiceTest extends TestCase
{
    public function testIsSsoShouldReturnTrue(): void
    {
        $loginConfigService = new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => 'c6a7ab8a-5c0c-4353-a38a-1b42479ef090',
                'client_secret' => '42fec3f9-a19b-4796-bce9-cb395a28da9f',
                'redirect_uri' => 'https://redirect.to',
                'base_url' => 'https://base.url',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            $this->createMock(RouterInterface::class)
        );

        $ssoService = new SsoService($loginConfigService);

        static::assertTrue($ssoService->isSso());
    }

    public function testIsSsoShouldReturnFalse(): void
    {
        // @phpstan-ignore argument.type (LoginConfigService expected an array with specific key-value pairs)
        $loginConfigService = new LoginConfigService([], $this->createMock(RouterInterface::class));

        $ssoService = new SsoService($loginConfigService);

        static::assertFalse($ssoService->isSso());
    }
}
