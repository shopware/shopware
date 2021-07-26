<?php declare(strict_types=1);

namespace App\Hmac\Guzzle;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;

class AuthMiddlewareTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    protected function setUp(): void
    {
        $this->resetHistory();
    }

    public function testSetDefaultHeader(): void
    {
        $middleware = new AuthMiddleware('6.4');
        $request = new Request('POST', 'https://example.local');

        $request = $middleware->getDefaultHeaderRequest($request);

        static::assertArrayHasKey('sw-version', $request->getHeaders());
        static::assertEquals('6.4', $request->getHeader('sw-version')[0]);
    }

    public function testSetDefaultHeaderExist(): void
    {
        $middleware = new AuthMiddleware('6.4');
        $request = new Request('POST', 'https://example.local', ['sw-version' => '6.5']);

        $request = $middleware->getDefaultHeaderRequest($request);

        static::assertArrayHasKey('sw-version', $request->getHeaders());
        static::assertEquals('6.5', $request->getHeader('sw-version')[0]);
    }

    public function testCorrectSignRequest(): void
    {
        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'secret',
            ],
                'body' => 'test', ];

        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');
        $client->post(new Uri('https://example.local'), $optionsRequest);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertArrayHasKey(RequestSigner::SHOPWARE_SHOP_SIGNATURE, $request->getHeaders());
    }

    public function testMissingRequiredResponseHeader(): void
    {
        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');
        $client->post(new Uri("'https://example.local'"));

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertArrayNotHasKey(RequestSigner::SHOPWARE_SHOP_SIGNATURE, $request->getHeaders());
    }

    public function testIncorrectInstanceOfOptionRequest(): void
    {
        static::expectException(InvalidArgumentException::class);

        $optionsRequest = [AuthMiddleware::APP_REQUEST_TYPE => new Response()];
        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');
        $client->post(new Uri("'https://example.local'"), $optionsRequest);
    }

    public function testInCorrectAuthenticResponse(): void
    {
        static::expectException(ServerException::class);

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::APP_SECRET => 'secret',
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
                'body' => 'test', ];

        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testOptionRequestArgumentException(): void
    {
        static::expectException(InvalidArgumentException::class);

        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => 'Not Array',
                'body' => 'test', ];

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }

    public function testOptionRequestMissingSecretArgumentException(): void
    {
        static::expectException(InvalidArgumentException::class);

        $this->appendNewResponse(new Response(200));

        $client = $this->getContainer()->get('shopware.app_system.guzzle');

        $optionsRequest
            = [AuthMiddleware::APP_REQUEST_TYPE => [
                AuthMiddleware::VALIDATED_RESPONSE => true,
            ],
                'body' => 'test', ];

        $client->post(new Uri('https://example.local'), $optionsRequest);
    }
}
