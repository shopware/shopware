<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Saas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Content\Saas\SaasService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SaasService::class)]
class SaasServiceTest extends TestCase
{
    public function testIsSaasShouldReturnTrue(): void
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
            'local.host',
            '/admin'
        );

        $saasService = new SaasService($loginConfigService);

        static::assertTrue($saasService->isSaas());
    }

    public function testIsSaasShouldReturnFalse(): void
    {
        $loginConfigService = new LoginConfigService([], 'local.host', '/admin');

        $saasService = new SaasService($loginConfigService);

        static::assertFalse($saasService->isSaas());
    }
}
