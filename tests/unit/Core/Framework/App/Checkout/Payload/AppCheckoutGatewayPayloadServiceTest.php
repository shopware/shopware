<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Checkout\Payload;

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
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayload;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(AppCheckoutGatewayPayloadService::class)]
#[Package('checkout')]
class AppCheckoutGatewayPayloadServiceTest extends TestCase
{
    public function testRequest(): void
    {
        $context = Generator::createSalesChannelContext();
        $cart = Generator::createCart();
        $paymentMethods = ['paymentMethod-1', 'paymentMethod-2'];
        $shippingMethods = ['shippingMethod-1', 'shippingMethod-2'];

        $app = new AppEntity();
        $app->setAppSecret('devsecret');

        $payload = new AppCheckoutGatewayPayload($context, $cart, $paymentMethods, $shippingMethods);
        $encodedPayload = $this->encodePayload($payload);

        $helper = $this->createMock(AppPayloadServiceHelper::class);
        $helper
            ->expects(static::once())
            ->method('encode')
            ->with($payload)
            ->willReturn($encodedPayload);

        $response = [
            [
                'command' => 'test-command',
                'payload' => ['test-payload'],
            ],
        ];

        $handler = new MockHandler();
        $handler->append($this->buildResponse($response));

        $client = new Client(['handler' => $handler]);

        $service = new AppCheckoutGatewayPayloadService(
            $helper,
            $client,
            $this->createMock(ExceptionLogger::class),
        );

        $gatewayResponse = $service->request('https://example.com', $payload, $app);

        static::assertInstanceOf(AppCheckoutGatewayResponse::class, $gatewayResponse);
        static::assertSame($response, $gatewayResponse->getCommands());
    }

    public function testRequestAppThrowsException(): void
    {
        $context = Generator::createSalesChannelContext();
        $cart = Generator::createCart();
        $paymentMethods = ['paymentMethod-1', 'paymentMethod-2'];
        $shippingMethods = ['shippingMethod-1', 'shippingMethod-2'];

        $app = new AppEntity();
        $app->setAppSecret('devsecret');

        $payload = new AppCheckoutGatewayPayload($context, $cart, $paymentMethods, $shippingMethods);

        $e = new BadResponseException('Bad', new Request('POST', 'https://example.com'), new Response());

        $handler = new MockHandler();
        $handler->append($e);

        $client = new Client(['handler' => $handler]);

        $logger = $this->createMock(ExceptionLogger::class);
        $logger
            ->expects(static::once())
            ->method('logOrThrowException')
            ->with($e);

        $service = new AppCheckoutGatewayPayloadService(
            $this->createMock(AppPayloadServiceHelper::class),
            $client,
            $logger,
        );

        $gatewayResponse = $service->request('https://example.com', $payload, $app);

        static::assertNull($gatewayResponse);
    }

    public function testRequestMissingAppSecret(): void
    {
        $context = Generator::createSalesChannelContext();
        $cart = Generator::createCart();
        $paymentMethods = ['paymentMethod-1', 'paymentMethod-2'];
        $shippingMethods = ['shippingMethod-1', 'shippingMethod-2'];

        $app = new AppEntity();
        $app->setName('Foo');
        $app->setAppSecret(null);

        $payload = new AppCheckoutGatewayPayload($context, $cart, $paymentMethods, $shippingMethods);

        $helper = $this->createMock(AppPayloadServiceHelper::class);
        $helper
            ->expects(static::once())
            ->method('encode')
            ->with($payload)
            ->willReturn([]);

        $service = new AppCheckoutGatewayPayloadService(
            $helper,
            new Client(),
            $this->createMock(ExceptionLogger::class),
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App registration for "Foo" failed: App secret is missing');

        $service->request('https://example.com', $payload, $app);
    }

    /**
     * @return array<string, mixed>
     */
    private function encodePayload(AppCheckoutGatewayPayload $payload): array
    {
        return [
            'salesChannelContext' => $payload->getSalesChannelContext()->jsonSerialize(),
            'cart' => $payload->getCart()->jsonSerialize(),
            'paymentMethods' => $payload->getPaymentMethods(),
            'shippingMethods' => $payload->getShippingMethods(),
        ];
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function buildResponse(array $body): ResponseInterface
    {
        return new Response(200, [], \json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
