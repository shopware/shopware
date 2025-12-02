<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\HealthCheckController;
use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(HealthCheckController::class)]
class HealthCheckControllerTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private SystemChecker&MockObject $systemChecker;

    public function testCheck(): void
    {
        $controller = $this->createHealthCheckController();
        $response = $controller->check(Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertFalse($response->isCacheable());
    }

    public function testSystemHealthCheck(): void
    {
        $controller = $this->createHealthCheckController(null, 'testToken');

        $extra = [
            'storeFrontUrl' => 'http://localhost/',
            'responseCode' => 200,
            'responseTime' => 0.07630205154418945,
        ];

        $result = new Result(
            'SaleChannelReadiness',
            Status::OK,
            'All sales channels are OK',
            true,
            $extra
        );
        $this->systemChecker->expects($this->once())
            ->method('check')
            ->willReturn([$result]);

        $request = Request::create('', 'GET', ['context' => 'pre_rollout']);
        $request->headers->set(HealthCheckController::HEADER_AUTHORIZATION, 'Bearer testToken');
        $response = $controller->health($request);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $expectedResponse = [
            'checks' => [
                [
                    'extensions' => [],
                    'name' => 'SaleChannelReadiness',
                    'healthy' => true,
                    'status' => 'OK',
                    'message' => 'All sales channels are OK',
                    'extra' => $extra,
                ],
            ],
        ];
        static::assertIsString($response->getContent());
        static::assertIsString(json_encode($expectedResponse));
        static::assertJsonStringEqualsJsonString(json_encode($expectedResponse), $response->getContent());
    }

    public function testEventIsDispatched(): void
    {
        $controller = $this->createHealthCheckController();
        $response = $controller->check(Context::createDefaultContext());

        static::assertCount(1, $this->eventDispatcher->getEvents());
        static::assertInstanceOf(HealthCheckEvent::class, $this->eventDispatcher->getEvents()[0]);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[DataProvider('authorizationTokenProvider')]
    public function testSystemHealthCheckAuthorization(
        bool $expectedOAuthServerException,
        ?string $headerValue = null,
        ?string $staticToken = null,
        ?string $validBearer = null
    ): void {
        $controller = $this->createHealthCheckController($staticToken, $validBearer);
        $request = Request::create('', 'GET', []);
        if ($headerValue !== null) {
            $request->headers->set(HealthCheckController::HEADER_AUTHORIZATION, $headerValue);
        }

        if ($expectedOAuthServerException) {
            static::expectException(OAuthServerException::class);
        }

        $response = $controller->health($request);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public static function authorizationTokenProvider(): \Generator
    {
        yield 'No auth header, no static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => null,
            'staticToken' => null,
            'validBearer' => null,
        ];
        yield 'No auth header, configured static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => null,
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
        yield 'wrong bearer auth header, configured static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => 'Bearer wrongToken',
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
        yield 'correct bearer auth header, configured static token' => [
            'expectedOAuthServerException' => false,
            'headerValue' => 'Bearer testBearerToken',
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
        yield 'wrong static auth header, no static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => 'Static testStaticToken',
            'staticToken' => null,
            'validBearer' => 'testBearerToken',
        ];
        yield 'correct static auth header, configured static token' => [
            'expectedOAuthServerException' => false,
            'headerValue' => 'Static testStaticToken',
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
        yield 'invalid auth header, configured static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => 'Static',
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
        yield 'unknown auth header, configured static token' => [
            'expectedOAuthServerException' => true,
            'headerValue' => 'Nothing',
            'staticToken' => 'testStaticToken',
            'validBearer' => 'testBearerToken',
        ];
    }

    private function createHealthCheckController(
        ?string $staticToken = null,
        ?string $validBearer = null
    ): HealthCheckController {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->systemChecker = $this->createMock(SystemChecker::class);

        $tokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $tokenValidator->method('validateAuthorization')->willReturnCallback(
            function (Request $request) use ($validBearer): void {
                // simplified mock of original implementation in src/Core/Framework/Api/OAuth/SymfonyBearerTokenValidator.php
                if ($request->headers->has(HealthCheckController::HEADER_AUTHORIZATION) === false) {
                    throw OAuthServerException::accessDenied('Missing "Authorization" header');
                }

                $header = $request->headers->get(HealthCheckController::HEADER_AUTHORIZATION, '');
                $jwt = \trim((string) \preg_replace('/^\s*Bearer\s/', '', $header));

                if (empty($validBearer) || $jwt !== $validBearer) {
                    throw OAuthServerException::accessDenied('Access token is invalid');
                }
            }
        );

        return new HealthCheckController(
            $this->eventDispatcher,
            $this->systemChecker,
            $tokenValidator,
            $staticToken,
        );
    }
}
