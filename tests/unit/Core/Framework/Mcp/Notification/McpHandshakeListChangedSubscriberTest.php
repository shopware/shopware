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

        (new McpHandshakeListChangedSubscriber())->onResponse($event);

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

        (new McpHandshakeListChangedSubscriber())->onResponse($event);

        static::assertSame($response, $event->getResponse());
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
