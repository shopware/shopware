<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Http\PinningAppSystemHttpClient;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PinningAppSystemHttpClient::class)]
class PinningAppSystemHttpClientTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $history;

    public function testPinsValidatedPublicIpLiteral(): void
    {
        $client = $this->createClient(static fn (string $host): array => []);

        $client->request('POST', 'https://93.184.216.34/webhook');

        static::assertSame(['93.184.216.34:443:93.184.216.34'], $this->getHistoryEntry(0)['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testRejectsPrivateIpLiteralBeforeTransport(): void
    {
        $client = $this->createClient(static fn (string $host): array => []);

        $this->expectExceptionObject(AppException::appSystemRequestTargetNotAllowed());

        try {
            $client->request('POST', 'https://10.0.0.10/webhook');
        } finally {
            static::assertCount(0, $this->history);
        }
    }

    public function testPinsEveryRedirectTarget(): void
    {
        $client = $this->createClient(
            static fn (string $host): array => match ($host) {
                'example.com' => [['ip' => '93.184.216.34']],
                'redirect.example.com' => [['ip' => '93.184.216.35']],
                default => [],
            },
            [
                new Response(302, ['Location' => 'https://redirect.example.com/target']),
                new Response(200),
            ],
        );

        $client->request('POST', 'https://example.com/source', ['body' => 'payload']);

        static::assertCount(2, $this->history);
        static::assertSame(['example.com:443:93.184.216.34'], $this->getHistoryEntry(0)['options']['curl'][\CURLOPT_RESOLVE]);
        static::assertSame(['redirect.example.com:443:93.184.216.35'], $this->getHistoryEntry(1)['options']['curl'][\CURLOPT_RESOLVE]);
        static::assertSame('GET', $this->getHistoryEntry(1)['request']->getMethod());
        static::assertSame('', (string) $this->getHistoryEntry(1)['request']->getBody());
    }

    /**
     * @param \Closure(string): list<array{ip?: string, ipv6?: string}> $dnsResolver
     * @param list<Response> $responses
     */
    private function createClient(\Closure $dnsResolver, array $responses = [new Response(200)]): ClientInterface
    {
        /** @var array<int, array<string, mixed>> $history */
        $history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        static::assertIsArray($history);
        $this->history = &$history;

        return new PinningAppSystemHttpClient(
            new Client(['handler' => $stack]),
            new WebhookTargetValidator(false, [], $dnsResolver, true),
        );
    }

    /**
     * @return array{request: \Psr\Http\Message\RequestInterface, options: array<string, mixed>}
     */
    private function getHistoryEntry(int $index): array
    {
        $history = $this->history;

        static::assertArrayHasKey($index, $history);

        $entry = $history[$index];
        static::assertArrayHasKey('request', $entry);
        static::assertArrayHasKey('options', $entry);
        static::assertInstanceOf(\Psr\Http\Message\RequestInterface::class, $entry['request']);
        static::assertIsArray($entry['options']);

        /** @var array<string, mixed> $options */
        $options = $entry['options'];

        return ['request' => $entry['request'], 'options' => $options];
    }
}
