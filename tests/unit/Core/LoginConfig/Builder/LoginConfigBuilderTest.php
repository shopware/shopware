<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\LoginConfig\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\LoginConfig\Builder\Handler\AbstractLoginConfigHandler;
use Shopware\Core\LoginConfig\Builder\Handler\SwSsoLogin;
use Shopware\Core\LoginConfig\Builder\LoginConfigBuilder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[CoversClass(LoginConfigBuilder::class)]
class LoginConfigBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $config = [
            'use_default' => false,
            'sso_providers' => [
                'swsso' => [
                    'snippet_key' => 'test-snippet-key',
                    'icon' => 'test-icon',
                    'class' => 'test-class',
                    'client_id' => 'test-client-id',
                    'client_secret' => 'test-client-secret',
                    'redirect_uri' => 'test-redirect-uri',
                    'base_url' => 'test-base-url',
                    'additional_data' => [
                        'test-key' => 'test-value',
                    ],
                ],
            ],
        ];

        $ssSsoLogin = new SwSsoLogin('http://appUrl', 'admin');
        $loginConfigBuilder = new LoginConfigBuilder($config, [$ssSsoLogin]);

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects(static::once())
            ->method('set')
            ->with('SSO_swsso');

        $result = $loginConfigBuilder->build($sessionMock);

        static::assertFalse($result['useDefault']);
        static::assertArrayHasKey('providers', $result);
        static::assertIsArray($result['providers']);
        static::assertCount(1, $result['providers']);

        foreach ($result['providers'] as $provider) {
            static::assertSame('swsso', $provider['key']);
            static::assertSame('test-snippet-key', $provider['snippet_key']);
            static::assertSame('test-icon', $provider['icon']);
            static::assertSame('test-class', $provider['class']);
            static::assertStringStartsWith('test-base-url/oauth/authorize?client_id=test-client-id&redirect_uri=test-redirect-uri&response_type=code&scope=openid&state=http://appUrl/admin+', \urldecode($provider['url']));
            static::assertSame(['test-key' => 'test-value'], $provider['additionalData']);
        }
    }

    public function testBuildWithNotMatchedBuilder(): void
    {
        $config = [
            'use_default' => true,
            'sso_providers' => [
                'anyConfigBuilder' => [],
                'anyOtherConfigBuilder' => [],
            ],
        ];

        $configBuilderMockOne = $this->createMock(AbstractLoginConfigHandler::class);
        $configBuilderMockOne->expects(static::exactly(2))
            ->method('supports')
            ->willReturn(false);

        $configBuilderMockTwo = $this->createMock(AbstractLoginConfigHandler::class);
        $configBuilderMockTwo->expects(static::exactly(2))
            ->method('supports')
            ->willReturn(false);

        $sessionMock = $this->createMock(SessionInterface::class);

        $loginConfigBuilder = new LoginConfigBuilder($config, [$configBuilderMockOne, $configBuilderMockTwo]);

        $result = $loginConfigBuilder->build($sessionMock);

        static::assertTrue($result['useDefault']);
        static::assertArrayHasKey('providers', $result);
        static::assertIsArray($result['providers']);
        static::assertCount(0, $result['providers']);
    }

    public function testBuildWithMock(): void
    {
        $config = [
            'use_default' => false,
            'sso_providers' => [
                'configBuilder' => [],
            ],
        ];

        $configBuilderMock = $this->createMock(AbstractLoginConfigHandler::class);
        $configBuilderMock->expects(static::once())
            ->method('createTemplateData')
            ->willReturn([]);

        $configBuilderMock->expects(static::once())
            ->method('supports')
            ->with('configBuilder')
            ->willReturn(true);

        $sessionMock = $this->createMock(SessionInterface::class);

        $loginConfigBuilder = new LoginConfigBuilder($config, [$configBuilderMock]);

        $result = $loginConfigBuilder->build($sessionMock);

        static::assertFalse($result['useDefault']);
        static::assertArrayHasKey('providers', $result);
        static::assertIsArray($result['providers']);
        static::assertCount(1, $result['providers']);
    }
}
