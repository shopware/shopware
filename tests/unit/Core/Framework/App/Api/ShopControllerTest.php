<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Api\ShopController;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ShopController::class)]
class ShopControllerTest extends TestCase
{
    private ShopController $controller;

    private ArrayAdapter $cache;

    private MockObject&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->controller = new ShopController($this->cache, $this->rateLimiter);
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

        $this->controller->verify($request);
    }

    #[DataProvider('requestProvider')]
    public function testController(Request $request, ?string $runId, ?string $token, int $expectedResponseCode): void
    {
        if ($token) {
            $this->cache->get(\sprintf('%s-%s', AppUrlVerifier::VERIFICATION_CACHE_KEY_PREFIX, $runId), fn () => $token);
        }

        $response = $this->controller->verify($request);

        static::assertSame($expectedResponseCode, $response->getStatusCode());
    }

    public static function requestProvider(): \Generator
    {
        yield 'no-ip-present' => [
            new Request(),
            null,
            null,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'no-run-id' => [
            new Request(
                query: ['token' => 'some-token'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            null,
            null,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'no-token' => [
            new Request(
                query: ['rid' => 'some-run-id'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            null,
            null,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'no-token-or-run-id' => [
            new Request(
                query: ['rid' => 'some-run-id'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            null,
            null,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'no-cache' => [
            new Request(
                query: [
                    'rid' => 'randomid',
                    'token' => bin2hex(random_bytes(16)),
                ],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            null,
            null,
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'invalid-stored-token' => [
            new Request(
                query: [
                    'rid' => 'randomid',
                    'token' => bin2hex(random_bytes(16)),
                ],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'randomid',
            bin2hex(random_bytes(14)),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'invalid-user-token' => [
            new Request(
                query: [
                    'rid' => 'randomid',
                    'token' => bin2hex(random_bytes(14)),
                ],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'randomid',
            bin2hex(random_bytes(16)),
            Response::HTTP_BAD_REQUEST,
        ];

        yield 'both-tokens-invalid' => [
            new Request(
                query: [
                    'rid' => 'randomid',
                    'token' => bin2hex(random_bytes(16)),
                ],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'randomid',
            bin2hex(random_bytes(16)),
            Response::HTTP_BAD_REQUEST,
        ];

        $token = bin2hex(random_bytes(16));
        yield 'success-tokens-match' => [
            new Request(
                query: [
                    'rid' => 'randomid',
                    'token' => $token,
                ],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'randomid',
            $token,
            Response::HTTP_NO_CONTENT,
        ];
    }
}
