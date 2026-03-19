<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Api\DTO\VerifyShop;
use Shopware\Core\Framework\App\Api\VerifyShopController;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(VerifyShopController::class)]
class VerifyShopControllerTest extends TestCase
{
    private VerifyShopController $controller;

    private AppUrlVerifier&MockObject $appUrlVerifier;

    private MockObject&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->appUrlVerifier = $this->createMock(AppUrlVerifier::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->controller = new VerifyShopController($this->rateLimiter, $this->appUrlVerifier);
    }

    public function testRateLimiter(): void
    {
        $e = new RateLimitExceededException(time());
        static::expectExceptionObject($e);

        $this->rateLimiter->expects($this->once())
            ->method('ensureAccepted')
            ->with(RateLimiter::APP_SHOP_VERIFY, '127.0.0.1')
            ->willThrowException($e);

        $request = new Request(
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $this->controller->verify(new VerifyShop('some-run-id', 'some-token'), $request);
    }

    #[DataProvider('requestProvider')]
    public function testControllerErrorConditions(VerifyShop $verifyShopRequest, Request $request, int $expectedResponseCode): void
    {
        $response = $this->controller->verify($verifyShopRequest, $request);

        static::assertSame($expectedResponseCode, $response->getStatusCode());
    }

    public static function requestProvider(): \Generator
    {
        yield 'no-ip-present' => [
            new VerifyShop('some-run-id', 'some-token'),
            new Request(),
            Response::HTTP_BAD_REQUEST,
        ];
    }

    public function testVerificationFailReturnsBadRequest(): void
    {
        $this->appUrlVerifier->expects($this->once())
            ->method('completeVerification')
            ->with('some-run-id', 'some-token')
            ->willReturn(false);

        $request = new Request(
            query: ['runId' => 'some-run-id', 'token' => 'some-token'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->controller->verify(new VerifyShop('some-run-id', 'some-token'), $request);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testVerificationPassReturnsNoContent(): void
    {
        $this->appUrlVerifier->expects($this->once())
            ->method('completeVerification')
            ->with('some-run-id', 'some-token')
            ->willReturn(true);

        $request = new Request(
            query: ['runId' => 'some-run-id', 'token' => 'some-token'],
            server: ['REMOTE_ADDR' => '127.0.0.1']
        );

        $response = $this->controller->verify(new VerifyShop('some-run-id', 'some-token'), $request);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
