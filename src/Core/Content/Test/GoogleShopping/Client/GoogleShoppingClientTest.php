<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleShoppingClientTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    protected function setUp(): void
    {
        skipTestNext6050($this);
    }

    public function testDeferExecute(): void
    {
        $client = new GoogleShoppingClient('clientId', 'clientSecret', 'redirectUri');

        // Default shouldDefer should be false
        static::assertFalse($client->shouldDefer());

        $callback = function () use ($client) {
            // Default shouldDefer should be true before execute callback
            $this->assertTrue($client->shouldDefer());

            return 'deferResult';
        };
        $result = $client->deferExecute($callback);

        static::assertEquals('deferResult', $result);

        // shouldDefer should be setback to false
        static::assertFalse($client->shouldDefer());
    }

    public function testAsyncRequestWithSkipErrors(): void
    {
        // Create a mock and queue two responses.
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['data' => 'First Request Response'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['data' => 'Second Request Response'])),
            new RequestException('Error Communicating with Server', new Request('GET', '/third')),
        ]);

        $handlerStack = HandlerStack::create($mock);

        $httpClient = new Client(['handler' => $handlerStack]);

        $googleShoppingClient = $this->getMockBuilder(GoogleShoppingClient::class)
            ->onlyMethods(['authorize'])
            ->disableOriginalConstructor()
            ->getMock();

        $googleShoppingClient->expects(static::once())->method('authorize')->willReturn($httpClient);

        $requests = [
            new Request('GET', '/first'),
            new Request('POST', '/second'),
            new Request('GET', '/third'),
        ];

        $skipErrors = true;

        $result = $googleShoppingClient->asyncRequests($requests, $skipErrors);

        static::assertNotEmpty($result);
        static::assertNotEmpty($result['responses']);
        static::assertNotEmpty($result['errors']);
        static::assertCount(2, $result['responses']);
        static::assertCount(1, $result['errors']);
        static::assertInstanceOf(RequestException::class, $result['errors'][0]);
        static::assertEquals([
            ['data' => 'First Request Response'],
            ['data' => 'Second Request Response'],
        ], $result['responses']);
    }

    public function testAsyncRequestWithoutSkipErrors(): void
    {
        $exception = new RequestException('Error Communicating with Server', new Request('GET', '/third'));

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage($exception->getMessage());
        $this->expectExceptionCode($exception->getCode());

        // Create a mock and queue two responses.
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['data' => 'First Request Response'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['data' => 'Second Request Response'])),
            $exception,
        ]);

        $handlerStack = HandlerStack::create($mock);

        $httpClient = new Client(['handler' => $handlerStack]);

        $googleShoppingClient = $this->getMockBuilder(GoogleShoppingClient::class)
            ->onlyMethods(['authorize'])
            ->disableOriginalConstructor()
            ->getMock();

        $googleShoppingClient->expects(static::once())->method('authorize')->willReturn($httpClient);

        $requests = [
            new Request('GET', '/first'),
            new Request('POST', '/second'),
            new Request('GET', '/third'),
        ];

        $skipErrors = false;

        $googleShoppingClient->asyncRequests($requests, $skipErrors);
    }
}
