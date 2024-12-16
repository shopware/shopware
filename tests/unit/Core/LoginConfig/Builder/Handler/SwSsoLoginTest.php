<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\LoginConfig\Builder\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\LoginConfig\Builder\Handler\SwSsoLogin;
use Shopware\Core\LoginConfig\Builder\LoginConfigItem;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[CoversClass(SwSsoLogin::class)]
class SwSsoLoginTest extends TestCase
{
    public function testCreateTemplateData(): void
    {
        $loginConfigItem = new LoginConfigItem(
            'test-key',
            'test-snippet-key',
            'test-icon',
            'test-class',
            'http://test-baseUrl',
            'test-client-id',
            'http://test-redirectUri',
        );

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects(static::once())
            ->method('set')
            ->with('SSO_test-key');

        $swSsoLogin = new SwSsoLogin('http://appUrl', 'admin');
        $swSsoLogin->setSession($sessionMock);

        $result = $swSsoLogin->createTemplateData($loginConfigItem);

        static::assertSame('test-key', $result['key']);
        static::assertSame('test-snippet-key', $result['snippet_key']);
        static::assertSame('test-icon', $result['icon']);
        static::assertSame('test-class', $result['class']);
        static::assertStringStartsWith('/oauth/authorize?client_id=http://test-baseUrl&redirect_uri=http://test-redirectUri&response_type=code&scope=openid&state=http://appUrl/admin+', \urldecode($result['url']));
        static::assertSame([], $result['additionalData']);
    }
}
