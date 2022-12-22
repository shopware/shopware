<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Test\Store\Services;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Core\Framework\Store\Services\OpenSSLVerifier;
use Shopware\Core\Framework\Store\Services\VerifyResponseSignatureMiddleware;

/**
 * @package merchant-services
 *
 * @internal
 * @covers \Shopware\Core\Framework\Store\Services\VerifyResponseSignatureMiddleware
 */
class VerifyResponseSignatureMiddlewareTest extends TestCase
{
    public function testReturnsResponseWithRewoundBody(): void
    {
        $response = new Response(200, ['X-Shopware-Signature' => 'v3rys3cr3t'], 'response body');

        $middleware = new VerifyResponseSignatureMiddleware(
            $this->createOpenSslVerifier(true, true)
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
        static::assertEquals('response body', $handledResponse->getBody()->getContents());
    }

    public function testReturnsResponseWithRewoundBodyIfSystemNotSupported(): void
    {
        $response = new Response(200, ['X-Shopware-Signature' => 'v3rys3cr3t'], 'response body');

        $middleware = new VerifyResponseSignatureMiddleware(
            $this->createOpenSslVerifier(false, true)
        );

        $handledResponse = $middleware($response);

        static::assertSame($response, $handledResponse);
        static::assertEquals('response body', $handledResponse->getBody()->getContents());
    }

    public function testThrowsIfSignatureHeaderIsMissing(): void
    {
        $response = new Response();

        $middleware = new VerifyResponseSignatureMiddleware(
            $this->createOpenSslVerifier(true, true)
        );

        $this->expectException(StoreSignatureValidationException::class);
        $middleware($middleware($response));
    }

    public function testThrowsIfSignatureHeaderIsEmpty(): void
    {
        $response = new Response(200, ['X-Shopware-Signature' => '']);

        $middleware = new VerifyResponseSignatureMiddleware(
            $this->createOpenSslVerifier(true, true)
        );

        $this->expectException(StoreSignatureValidationException::class);
        $middleware($middleware($response));
    }

    public function testThrowsIfSignatureIsInvalid(): void
    {
        $response = new Response(200, ['X-Shopware-Signature' => 'v3rys3cr3t']);

        $middleware = new VerifyResponseSignatureMiddleware(
            $this->createOpenSslVerifier(true, false)
        );

        $this->expectException(StoreSignatureValidationException::class);
        $middleware($middleware($response));
    }

    /**
     * @return OpenSSLVerifier|MockObject
     */
    private function createOpenSslVerifier(bool $isSystemSupported, bool $isValid): MockObject
    {
        $openSslVerifier = $this->createMock(OpenSSLVerifier::class);

        $openSslVerifier->method('isSystemSupported')
            ->willReturn($isSystemSupported);

        $openSslVerifier->method('isValid')
            ->willReturn($isValid);

        return $openSslVerifier;
    }
}
