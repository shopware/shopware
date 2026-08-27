<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSystemHttpMiddleware::class)]
class AppSystemHttpMiddlewareTest extends TestCase
{
    public function testPinsTheValidatedInitialRequest(): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => ['93.184.216.34']);

        $client->post('https://example.com/webhook');

        static::assertSame(['example.com:443:93.184.216.34'], $history[0]['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testAllowsPublicIpLiteralInAppMode(): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => [], webhookMode: false);

        $client->post('https://93.184.216.34/webhook');

        static::assertSame(['93.184.216.34:443:93.184.216.34'], $history[0]['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testRejectsPublicIpLiteralInWebhookModeBeforeTransport(): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => [], webhookMode: true);

        $this->expectExceptionObject(AppException::appSystemRequestNotAllowed('App system request target is not allowed.'));

        try {
            $client->post('https://93.184.216.34/webhook');
        } finally {
            static::assertCount(0, $history);
        }
    }

    public function testAllowsExactAllowlistedPrivateIpLiteralInWebhookMode(): void
    {
        $history = [];
        $client = $this->createClient(
            $history,
            static fn (): array => [],
            webhookMode: true,
            allowedPrivateIpAddresses: ['10.0.0.10'],
        );

        $client->post('https://10.0.0.10/webhook');

        static::assertSame(['10.0.0.10:443:10.0.0.10'], $history[0]['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testUsesNativeRedirectTransformationAndPinsRedirectTarget(): void
    {
        $history = [];
        $client = $this->createClient(
            $history,
            static fn (string $host): array => match ($host) {
                'example.com' => ['93.184.216.34'],
                'redirect.example.com' => ['93.184.216.35'],
                default => [],
            },
            responses: [
                new Response(302, ['Location' => 'https://redirect.example.com/target']),
                new Response(200),
            ],
        );

        $client->post('https://example.com/source', ['body' => 'payload']);

        static::assertCount(2, $history);
        /** @var array{request: RequestInterface, options: array<string, mixed>} $redirect */
        $redirect = $history[1];
        static::assertSame('GET', $redirect['request']->getMethod());
        static::assertSame('', (string) $redirect['request']->getBody());
        static::assertSame(
            ['redirect.example.com:443:93.184.216.35'],
            $history[1]['options']['curl'][\CURLOPT_RESOLVE],
        );
    }

    public function testReplacesCallerProvidedCurlResolveWithTheValidatedAddress(): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => ['93.184.216.34']);

        $client->post('https://example.com/webhook', ['curl' => [\CURLOPT_RESOLVE => ['example.com:443:127.0.0.1']]]);

        static::assertSame(['example.com:443:93.184.216.34'], $history[0]['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testDisablesEnvironmentProxyFallbackAfterValidation(): void
    {
        $originalHttpsProxy = getenv('HTTPS_PROXY', true);
        putenv('HTTPS_PROXY');

        try {
            $history = [];
            $client = $this->createClient($history, static fn (): array => ['93.184.216.34']);
            putenv('HTTPS_PROXY=http://proxy.example.com:8080');

            $client->post('https://example.com/webhook');

            static::assertSame(['http' => '', 'https' => ''], $history[0]['options']['proxy']);
        } finally {
            putenv($originalHttpsProxy === false ? 'HTTPS_PROXY' : 'HTTPS_PROXY=' . $originalHttpsProxy);
        }
    }

    public function testBlocksPrivateRedirectBeforeTransport(): void
    {
        $history = [];
        $client = $this->createClient(
            $history,
            static fn (string $host): array => $host === 'example.com' ? ['93.184.216.34'] : ['10.0.0.10'],
            responses: [new Response(302, ['Location' => 'https://private.example.com/target'])],
        );

        $this->expectExceptionObject(AppException::appSystemRequestNotAllowed('App system request target is not allowed.'));

        try {
            $client->post('https://example.com/source');
        } finally {
            static::assertCount(1, $history);
        }
    }

    public function testRejectsHttpUnlessUnencryptedTrafficIsAllowed(): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => ['93.184.216.34']);

        $this->expectExceptionObject(AppException::appSystemRequestNotAllowed('App system request target is not allowed.'));

        try {
            $client->post('http://example.com/webhook');
        } finally {
            static::assertCount(0, $history);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('unsafeRequestOptions')]
    public function testRejectsUnsafeConnectionOverrides(array $options, string $message): void
    {
        $history = [];
        $client = $this->createClient($history, static fn (): array => ['93.184.216.34']);

        $this->expectExceptionObject(AppException::appSystemRequestNotAllowed($message));

        try {
            $client->post('https://example.com/webhook', $options);
        } finally {
            static::assertCount(0, $history);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unsafeRequestOptions(): iterable
    {
        yield 'proxy' => [['proxy' => 'http://proxy.example.com:8080'], 'App system requests cannot use a proxy.'];
        yield 'curl proxy' => [['curl' => [\CURLOPT_PROXY => 'proxy.example.com']], 'App system requests cannot use a proxy.'];
        yield 'connect-to target override' => [['curl' => [\CURLOPT_CONNECT_TO => ['example.com:443:127.0.0.1:443']]], 'App system requests cannot override the validated connection target.'];
        yield 'Unix socket target override' => [['curl' => [\CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock']], 'App system requests cannot override the validated connection target.'];
        yield 'raw URL target override' => [['curl' => [\CURLOPT_URL => 'https://internal.example.com/']], 'App system requests cannot override the validated connection target.'];
        yield 'raw port target override' => [['curl' => [\CURLOPT_PORT => 8080]], 'App system requests cannot override the validated connection target.'];
        yield 'Alt-Svc cache target override' => [['curl' => [\CURLOPT_ALTSVC => '/tmp/alt-svc-cache']], 'App system requests cannot override the validated connection target.'];
        yield 'Alt-Svc routing target override' => [['curl' => [\CURLOPT_ALTSVC_CTRL => \CURLALTSVC_H1]], 'App system requests cannot override the validated connection target.'];
        yield 'native redirects' => [['curl' => [\CURLOPT_FOLLOWLOCATION => true]], 'App system requests cannot bypass redirect validation.'];
        yield 'Guzzle IP resolution' => [['force_ip_resolve' => 'v4'], 'App system requests cannot override the validated IP resolution.'];
        yield 'curl IP resolution' => [['curl' => [\CURLOPT_IPRESOLVE => \CURL_IPRESOLVE_V4]], 'App system requests cannot override the validated IP resolution.'];
        yield 'raw headers' => [['curl' => [\CURLOPT_HTTPHEADER => ['Authorization: Bearer secret']]], 'App system requests cannot override validated request headers.'];
    }

    /**
     * @param list<Response> $responses
     * @param \Closure(string): list<string> $dnsResolver
     * @param list<array{request: RequestInterface, options: array<string, mixed>}> $history
     * @param list<string> $allowedPrivateIpAddresses
     *
     * @param-out list<array{request: RequestInterface, options: array<string, mixed>}> $history
     */
    private function createClient(
        array &$history,
        \Closure $dnsResolver,
        array $responses = [new Response(200)],
        bool $webhookMode = false,
        array $allowedPrivateIpAddresses = [],
    ): Client {
        /** @var list<array{request: RequestInterface, options: array<string, mixed>}> $history */
        $history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->after(
            'allow_redirects',
            new AppSystemHttpMiddleware(
                new TrustedUrlResolver($dnsResolver, allowedPrivateIps: $allowedPrivateIpAddresses),
                false,
                $webhookMode,
                $allowedPrivateIpAddresses,
            ),
            'app_system_http_security',
        );
        $stack->after('app_system_http_security', static function (callable $handler) use (&$history): callable {
            return static function (RequestInterface $request, array $options) use (&$history, $handler): PromiseInterface {
                $history[] = ['request' => $request, 'options' => $options];

                return $handler($request, $options);
            };
        }, 'history');

        return new Client(['handler' => $stack]);
    }
}
