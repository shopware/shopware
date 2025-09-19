<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\LoginResponseService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoginResponseService::class)]
class LoginResponseServiceTest extends TestCase
{
    public function testCreate(): void
    {
        $response = new Response(
            200,
            [],
            (string) json_encode(
                [
                    'access_token' => Uuid::randomHex(),
                    'refresh_token' => Uuid::randomHex(),
                    'expires_in' => 3600,
                ]
            )
        );

        $result = $this->createLoginResponseService()->create($response);

        $cookie = $result->headers->getCookies()[0];
        static::assertSame('bearerAuth', $cookie->getName());
        static::assertSame('/admin', $cookie->getPath());
        static::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        static::assertIsInt($cookie->getExpiresTime());

        $cookieValueString = $cookie->getValue();
        static::assertIsString($cookieValueString);

        $cookieValue = \json_decode($cookieValueString, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($cookieValue);
        static::assertArrayHasKey('access', $cookieValue);
        static::assertArrayHasKey('refresh', $cookieValue);
        static::assertArrayHasKey('expiry', $cookieValue);
    }

    private function createLoginResponseService(): LoginResponseService
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())->method('generate')->willReturn('/admin');

        return new LoginResponseService($urlGenerator);
    }
}
