<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Agent\NativeMcpToolClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(NativeMcpToolClient::class)]
class NativeMcpToolClientTest extends TestCase
{
    public function testInitializesSessionOnceAndCallsToolsAsSubrequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn', server: [
            'HTTP_AUTHORIZATION' => 'Bearer administration-token',
        ]));
        $calls = 0;
        $kernel->expects($this->exactly(3))
            ->method('handle')
            ->willReturnCallback(static function (Request $request, int $type) use (&$calls): Response {
                ++$calls;
                static::assertSame(HttpKernelInterface::SUB_REQUEST, $type);
                static::assertSame('Bearer administration-token', $request->headers->get('Authorization'));
                $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

                if ($calls === 1) {
                    static::assertSame('initialize', $payload['method']);

                    return new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']);
                }

                static::assertSame('tools/call', $payload['method']);
                static::assertSame('session-id', $request->headers->get('Mcp-Session-Id'));

                return new JsonResponse([
                    'result' => [
                        'content' => [['type' => 'text', 'text' => '{"success":true,"data":{"layout":[]}}']],
                    ],
                ]);
            });

        $client = new NativeMcpToolClient($kernel, $requestStack);

        static::assertSame(
            '{"success":true,"data":{"layout":[]}}',
            $client->call('shopware-content-layout-diagnose', ['layout' => '[]']),
        );
        static::assertSame(
            '{"success":true,"data":{"layout":[]}}',
            $client->call('shopware-content-layout-diagnose', ['layout' => '[]']),
        );
    }

    public function testEnrichesUnhandledToolErrorWithSafeDiagnostics(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnOnConsecutiveCalls(
            new JsonResponse(['result' => []], headers: ['mcp-session-id' => 'session-id']),
            new JsonResponse([
                'result' => [
                    'content' => [['type' => 'text', 'text' => '{"success":false,"error":"Error while executing tool"}']],
                    'isError' => true,
                ],
            ]),
        );
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_action/experience-studio-agent/turn'));
        $client = new NativeMcpToolClient($kernel, $requestStack);

        $result = json_decode($client->call('shopware-content-layout-configure', [
            'layout' => '[{"id":"element-id","properties":{"text":"private layout content"}}]',
            'elementId' => 'element-id',
            'properties' => '{"text":"Updated"}',
        ]), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('shopware-content-layout-configure', $result['error']);
        static::assertStringContainsString('"elementId":"element-id"', $result['error']);
        static::assertStringContainsString('"topLevelElements":1', $result['error']);
        static::assertStringNotContainsString('private layout content', $result['error']);
    }
}
