<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\AuthenticationProvider;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @package merchant-services
 *
 * @internal
 * @covers \Shopware\Core\Framework\Store\Authentication\AuthenticationProvider
 * @DisabledFeatures(features={"v6.5.0.0"})
 */
class AuthenticationProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderDelegatesToOptionsProvider(): void
    {
        $context = Context::createDefaultContext();

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->with($context)
            ->willReturn([
                'X-Shopware-Platform-Token' => 'sbp-token',
            ]);

        $authenticationProvider = new AuthenticationProvider($optionsProvider);

        $authenticationHeader = $authenticationProvider->getAuthenticationHeader($context);

        static::assertEquals([
            'X-Shopware-Platform-Token' => 'sbp-token',
        ], $authenticationHeader);
    }

    public function testGetUserStoreTokenReturnsTokenIfPresent(): void
    {
        $context = Context::createDefaultContext();

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->with($context)
            ->willReturn([
                'X-Shopware-Platform-Token' => 'sbp-token',
            ]);

        $authenticationProvider = new AuthenticationProvider($optionsProvider);

        $userToken = $authenticationProvider->getUserStoreToken($context);

        static::assertEquals('sbp-token', $userToken);
    }

    public function testGetUserStoreTokenReturnsNullIfTokenIsMissing(): void
    {
        $context = Context::createDefaultContext();

        $optionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);
        $optionsProvider->expects(static::once())
            ->method('getAuthenticationHeader')
            ->with($context)
            ->willReturn([]);

        $authenticationProvider = new AuthenticationProvider($optionsProvider);

        $userToken = $authenticationProvider->getUserStoreToken($context);

        static::assertNull($userToken);
    }
}
