<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Hmac\RequestSigner;

/**
 * @internal
 */
class RequestSignerTest extends TestCase
{
    private string $authSecret;

    protected function setUp(): void
    {
        $this->authSecret = 'lksf#$osck$FSFDSF#$#F43jjidjsfisj-333';
    }

    public function testSignHeaderAddedRequest(): void
    {
        $body = '{"method":"hi.nam","params":["1","2","3"]}';
        $hashExpected = hash_hmac('sha256', $body, $this->authSecret);

        $request = new Request('POST', 'https://example.local', [], $body);

        $post = new RequestSigner();

        $request = $post->signRequest($request, $this->authSecret);

        static::assertTrue($request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));

        static::assertSame($hashExpected, $request->getHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE)[0]);
    }

    public function testSignHeaderWithoutAddedMethodGet(): void
    {
        $request = new Request('GET', 'https://example.local', []);

        $post = new RequestSigner();

        $request = $post->signRequest($request, $this->authSecret);

        static::assertFalse($request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }

    public function testSignHeaderWithoutAddedNoBody(): void
    {
        $request = new Request('POST', 'https://example.local', []);

        $post = new RequestSigner();

        $request = $post->signRequest($request, $this->authSecret);

        static::assertFalse($request->hasHeader(RequestSigner::SHOPWARE_SHOP_SIGNATURE));
    }

    public function testIsResponseAuthenticRequired(): void
    {
        $body = '{"method":"hi.nam","params":["1","2","3"]}';

        $post = new RequestSigner();
        $signature = $post->signPayload($body, $this->authSecret);

        $responseHeaders = [
            RequestSigner::SHOPWARE_APP_SIGNATURE => $signature,
        ];

        $response = new Response(200, $responseHeaders, $body);

        static::assertTrue($post->isResponseAuthentic($response, $this->authSecret));
        static::assertNotEmpty($response->getBody()->getContents());
    }

    public function testIsResponseAuthenticRequiredWithoutHeader(): void
    {
        $response = new Response(200);

        $post = new RequestSigner();

        static::assertFalse($post->isResponseAuthentic($response, $this->authSecret));
    }

    public function testIsResponseAuthenticRequiredNoBody(): void
    {
        $post = new RequestSigner();
        $signature = $post->signPayload('No-Body', $this->authSecret);

        $responseHeaders = [
            RequestSigner::SHOPWARE_APP_SIGNATURE => $signature,
        ];

        $response = new Response(200, $responseHeaders);

        static::assertFalse($post->isResponseAuthentic($response, $this->authSecret));
    }
}
