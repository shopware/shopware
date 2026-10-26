<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Mcp\Event\ResponseEvent;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\PingRequest;
use Mcp\Schema\Result\EmptyResult;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpHandshakeListChangedSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpHandshakeListChangedSubscriber::class)]
class McpHandshakeListChangedSubscriberTest extends TestCase
{
    public function testSubscribesToTheSdkResponseEvent(): void
    {
        static::assertSame(
            [ResponseEvent::class => 'onResponse'],
            McpHandshakeListChangedSubscriber::getSubscribedEvents(),
        );
    }

    public function testDeclaresListChangedForEveryAdvertisedListOfTheInitializeAnswer(): void
    {
        $result = new InitializeResult(
            new ServerCapabilities(tools: true, resources: true, resourcesSubscribe: true, prompts: false, logging: true, completions: true),
            new Implementation('Shopware', '1.0.0'),
            'instructions',
            ['shopware' => ['user' => ['id' => 'user-id']]],
            ProtocolVersion::V2025_06_18,
        );
        $event = $this->event(new InitializeRequest('2025-06-18', new ClientCapabilities(), new Implementation('client', '1.0')), $result);

        $this->subscriber()->onResponse($event);

        $response = $event->getResponse();
        static::assertSame(1, $response->id);

        $answer = $response->result;
        static::assertInstanceOf(InitializeResult::class, $answer);
        static::assertTrue($answer->capabilities->toolsListChanged);
        static::assertTrue($answer->capabilities->resourcesListChanged);
        static::assertFalse($answer->capabilities->promptsListChanged, 'a list the server does not advertise gets no flag');
        static::assertTrue($answer->capabilities->resourcesSubscribe);
        static::assertTrue($answer->capabilities->logging);
        static::assertTrue($answer->capabilities->completions);
        static::assertSame($result->serverInfo, $answer->serverInfo);
        static::assertSame('instructions', $answer->instructions);
        static::assertSame($result->meta, $answer->meta);
        static::assertSame(ProtocolVersion::V2025_06_18, $answer->protocolVersion);
    }

    public function testLeavesOtherResponsesUntouched(): void
    {
        $event = $this->event(new PingRequest(), new EmptyResult());
        $response = $event->getResponse();

        $this->subscriber()->onResponse($event);

        static::assertSame($response, $event->getResponse());
    }

    public function testLeavesTheInitializeAnswerOfAnotherMcpServerUntouched(): void
    {
        $event = $this->event(
            new InitializeRequest('2025-06-18', new ClientCapabilities(), new Implementation('client', '1.0')),
            new InitializeResult(new ServerCapabilities(tools: true), new Implementation('Plugin server', '1.0.0')),
        );
        $response = $event->getResponse();

        $this->subscriber('_mcp_plugin')->onResponse($event);

        static::assertSame($response, $event->getResponse());
    }

    public function testDeclaresListChangedOnTheStoreApiEndpoint(): void
    {
        $event = $this->event(
            new InitializeRequest('2025-06-18', new ClientCapabilities(), new Implementation('client', '1.0')),
            new InitializeResult(new ServerCapabilities(tools: true), new Implementation('Shopware Store API', '1.0.0')),
        );

        $this->subscriber('store-api.mcp.endpoint')->onResponse($event);

        $answer = $event->getResponse()->result;
        static::assertInstanceOf(InitializeResult::class, $answer);
        static::assertTrue($answer->capabilities->toolsListChanged);
    }

    private function subscriber(string $route = 'api.mcp.endpoint'): McpHandshakeListChangedSubscriber
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(attributes: ['_route' => $route]));

        return new McpHandshakeListChangedSubscriber($requestStack);
    }

    private function event(InitializeRequest|PingRequest $request, InitializeResult|EmptyResult $result): ResponseEvent
    {
        return new ResponseEvent(
            new Response(1, $result),
            $request->withId(1),
            static::createStub(SessionInterface::class),
        );
    }
}
