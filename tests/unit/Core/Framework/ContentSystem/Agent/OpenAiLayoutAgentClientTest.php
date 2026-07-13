<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Agent\NativeMcpToolClient;
use Shopware\Core\Framework\ContentSystem\Agent\OpenAiLayoutAgentClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(OpenAiLayoutAgentClient::class)]
class OpenAiLayoutAgentClientTest extends TestCase
{
    public function testReturnsFinalLayoutAfterToolCall(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'function_call',
                    'name' => 'shopware-content-layout-compose',
                    'call_id' => 'call-1',
                    'arguments' => json_encode([
                        'insertions' => [[
                            'alias' => 'headline',
                            'type' => 'Sw:Content:Text',
                            'properties' => ['text' => '<h2>Welcome to Summer!</h2>'],
                        ]],
                    ], \JSON_THROW_ON_ERROR),
                ]],
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Added the summer headline.']],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnOnConsecutiveCalls(
            new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']),
            new JsonResponse([
                'result' => [
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"success":true,"data":{"layout":[{"id":"text-id","component":"Sw:Content:Text","properties":{"text":"<h2>Welcome to Summer!</h2>"}}]}}',
                    ]],
                ],
            ]),
        );
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn'));
        $client = new OpenAiLayoutAgentClient(
            $httpClient,
            'open-ai-key',
            new NativeMcpToolClient($kernel, $requestStack),
        );

        $result = $client->respond(
            [['role' => 'user', 'content' => 'Add a summer headline']],
            [],
            'product',
            null,
            [],
            [],
        );

        static::assertSame('Added the summer headline.', $result['message']);
        static::assertSame('<h2>Welcome to Summer!</h2>', $result['layout'][0]['properties']['text']);
    }

    public function testMediaSearchIsRestrictedToMediaEntity(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'function_call',
                    'name' => 'shopware-content-media-search',
                    'call_id' => 'call-1',
                    'arguments' => '{"query":"summer beach","limit":5}',
                ]],
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Found a summer image.']],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $call = 0;
        $kernel->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(static function (Request $request) use (&$call): JsonResponse {
                ++$call;
                if ($call === 1) {
                    return new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']);
                }

                $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                static::assertSame('shopware-entity-search', $payload['params']['name']);
                static::assertSame('media', $payload['params']['arguments']['entity']);
                static::assertSame('summer beach', $payload['params']['arguments']['term']);
                static::assertSame(5, $payload['params']['arguments']['limit']);

                return new JsonResponse([
                    'result' => [
                        'content' => [['type' => 'text', 'text' => '{"success":true,"data":[{"id":"media-id"}]}']],
                    ],
                ]);
            });
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn'));
        $client = new OpenAiLayoutAgentClient(
            $httpClient,
            'open-ai-key',
            new NativeMcpToolClient($kernel, $requestStack),
        );

        $result = $client->respond(
            [['role' => 'user', 'content' => 'Use a summer beach image']],
            [],
            'product',
            null,
            [],
            [],
        );

        static::assertSame('Found a summer image.', $result['message']);
    }

    public function testNormalizesSingleWrapElementIdToList(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'function_call',
                    'name' => 'shopware-content-layout-mutate',
                    'call_id' => 'call-1',
                    'arguments' => json_encode([
                        'operation' => 'wrap',
                        'request' => [
                            'elementIds' => 'text-id',
                            'containerType' => 'Sw:Grid:Container',
                        ],
                    ], \JSON_THROW_ON_ERROR),
                ]],
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Wrapped the text.']],
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $call = 0;
        $kernel->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(static function (Request $request) use (&$call): JsonResponse {
                ++$call;
                if ($call === 1) {
                    return new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']);
                }

                $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                $mutationRequest = json_decode($payload['params']['arguments']['request'], true, 512, \JSON_THROW_ON_ERROR);
                static::assertSame(['text-id'], $mutationRequest['elementIds']);

                return new JsonResponse([
                    'result' => [
                        'content' => [['type' => 'text', 'text' => '{"success":true,"data":{"layout":[]}}']],
                    ],
                ]);
            });
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn'));
        $client = new OpenAiLayoutAgentClient(
            $httpClient,
            'open-ai-key',
            new NativeMcpToolClient($kernel, $requestStack),
        );

        $result = $client->respond(
            [['role' => 'user', 'content' => 'Wrap the existing text']],
            [['id' => 'text-id', 'component' => 'Sw:Content:Text']],
            'product',
            'text-id',
            [],
            [],
        );

        static::assertSame('Wrapped the text.', $result['message']);
    }

    public function testDoesNotRetargetStaleElementIdToUiSelection(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [[
                    'type' => 'function_call',
                    'name' => 'shopware-content-layout-configure',
                    'call_id' => 'call-1',
                    'arguments' => '{"elementId":"stale-id","properties":{"text":"Updated"}}',
                ]],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $call = 0;
        $kernel->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(static function (Request $request) use (&$call): JsonResponse {
                ++$call;
                if ($call === 1) {
                    return new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']);
                }

                $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                static::assertSame('stale-id', $payload['params']['arguments']['elementId']);

                return new JsonResponse([
                    'result' => ['content' => [['type' => 'text', 'text' => '{"success":false,"error":"Element stale-id not found"}']]],
                ]);
            });
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn'));
        $client = new OpenAiLayoutAgentClient(
            $httpClient,
            'open-ai-key',
            new NativeMcpToolClient($kernel, $requestStack),
        );

        $result = $client->respond(
            [['role' => 'user', 'content' => 'Update the text']],
            [['id' => 'current-id', 'component' => 'Sw:Content:Text']],
            'product',
            'current-id',
            [],
            [],
        );

        static::assertSame('I could not apply that layout change: Element stale-id not found', $result['message']);
        static::assertNull($result['layout']);
    }
}
