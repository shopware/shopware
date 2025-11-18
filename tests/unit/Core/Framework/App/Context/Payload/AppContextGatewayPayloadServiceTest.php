<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Context\Payload;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\AppPayloadStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppContextGatewayPayloadService::class)]
#[Package('framework')]
class AppContextGatewayPayloadServiceTest extends TestCase
{
    public function testRequest(): void
    {
        $context = Generator::generateSalesChannelContext();
        $cart = Generator::createCart();
        $customData = new RequestDataBag(['foo' => 'bar']);

        $app = new AppEntity();
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $payload = new ContextGatewayPayloadStruct($cart, $context, $customData);
        $encodedPayload = \json_encode($this->encodePayload($payload), \JSON_THROW_ON_ERROR);

        $helper = $this->createMock(AppPayloadServiceHelper::class);
        $helper
            ->expects($this->once())
            ->method('createRequestOptions')
            ->with($payload, $app, $context->getContext())
            ->willReturn($this->buildTestPayload($context->getContext(), $encodedPayload));

        $response = [
            [
                'command' => 'context_test-command',
                'payload' => ['foo' => 'bar'],
            ],
        ];

        $handler = new MockHandler();
        $handler->append($this->buildResponse($response));

        $client = new Client(['handler' => $handler]);

        $service = new AppContextGatewayPayloadService($helper, $client);

        $gatewayResponse = $service->request('https://example.com', $payload, $app);

        static::assertInstanceOf(AppContextGatewayResponse::class, $gatewayResponse);
        static::assertSame($response, $gatewayResponse->getCommands());
    }

    public function testRequestAppThrowsException(): void
    {
        $context = Generator::generateSalesChannelContext();
        $cart = Generator::createCart();
        $customData = new RequestDataBag(['foo' => 'bar']);

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setVersion('1.0.0');
        $app->setAppSecret('devsecret');

        $payload = new ContextGatewayPayloadStruct($cart, $context, $customData);

        $e = new BadResponseException('Bad', new Request('POST', 'https://example.com'), new Response());

        $handler = new MockHandler();
        $handler->append($e);

        $client = new Client(['handler' => $handler]);

        $service = new AppContextGatewayPayloadService(
            $this->createMock(AppPayloadServiceHelper::class),
            $client,
        );

        $this->expectExceptionObject(AppException::gatewayRequestFailed('TestApp', 'context', $e));

        $service->request('https://example.com', $payload, $app);
    }

    /**
     * @return array<string, mixed>
     */
    private function encodePayload(ContextGatewayPayloadStruct $payload): array
    {
        return [
            'salesChannelContext' => $payload->getSalesChannelContext()->jsonSerialize(),
            'cart' => $payload->getCart()->jsonSerialize(),
            'data' => $payload->getData(),
        ];
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function buildResponse(array $body): ResponseInterface
    {
        return new Response(200, [], \json_encode($body, \JSON_THROW_ON_ERROR));
    }

    private function buildTestPayload(Context $context, string $payload): AppPayloadStruct
    {
        return new AppPayloadStruct([
            AuthMiddleware::APP_REQUEST_CONTEXT => $context,
            AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'some-secret',
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $payload,
        ]);
    }
}
