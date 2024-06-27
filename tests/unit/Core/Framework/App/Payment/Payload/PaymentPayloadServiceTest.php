<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Payload;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayloadInterface;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentPayloadService::class)]
class PaymentPayloadServiceTest extends TestCase
{
    private ClientInterface&MockObject $client;

    private AppPayloadServiceHelper&MockObject $helper;

    private PaymentPayloadService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->helper = $this->createMock(AppPayloadServiceHelper::class);
        $this->service = new PaymentPayloadService($this->helper, $this->client);
    }

    public function testRequestReturnsExpectedResponse(): void
    {
        $payload = $this->createMock(PaymentPayloadInterface::class);
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setAppSecret('secret');

        $context = Context::createDefaultContext();

        $this->helper
            ->expects(static::once())
            ->method('encode')
            ->with($payload)
            ->willReturn([]);

        $this->helper
            ->expects(static::once())
            ->method('buildSource')
            ->with($app)
            ->willReturn(new Source('shopurl', 'shopid', '0.0.0'));

        $this->client
            ->expects(static::once())
            ->method('request')
            ->with('POST', 'http://example.com', [
                AuthMiddleware::APP_REQUEST_CONTEXT => $context,
                AuthMiddleware::APP_REQUEST_TYPE => [
                    AuthMiddleware::APP_SECRET => 'secret',
                    AuthMiddleware::VALIDATED_RESPONSE => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => '[]',
            ])
            ->willReturn(new Response(200, [], json_encode(['message' => 'foo'], \JSON_THROW_ON_ERROR)));

        $response = $this->service->request(
            'http://example.com',
            $payload,
            $app,
            PaymentResponse::class,
            $context,
        );

        static::assertInstanceOf(PaymentResponse::class, $response);
        static::assertSame('foo', $response->getErrorMessage());
    }

    public function testRequestThrowsExceptionWhenAppSecretIsMissing(): void
    {
        $app = new AppEntity();
        $app->setName('InsecureApp');
        $app->setAppSecret(null);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App registration for "InsecureApp" failed: App secret is missing');
        $this->service->request(
            'http://example.com',
            $this->createMock(PaymentPayloadInterface::class),
            $app,
            AbstractResponse::class,
            Context::createDefaultContext()
        );
    }
}
