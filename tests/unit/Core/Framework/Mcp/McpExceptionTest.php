<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Exception\PromptGetException;
use Mcp\Exception\ResourceReadException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpException::class)]
class McpExceptionTest extends TestCase
{
    public function testThrottledPreservesPreviousException(): void
    {
        $previous = new \RuntimeException('rate limit hit');
        $e = McpException::throttled(30, $previous);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $e->getStatusCode());
        static::assertSame('MCP__THROTTLED', $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testOAuthProtectedResourceMetadataUnavailableIsNotFoundWithTheReason(): void
    {
        $previous = new \InvalidArgumentException('must use https');
        $e = McpException::oauthProtectedResourceMetadataUnavailable('http://shop.example/api/_mcp', $previous);

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame('MCP__OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE', $e->getErrorCode());
        static::assertSame('No OAuth protected resource metadata for "http://shop.example/api/_mcp": must use https', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testToolResultNotFoundNamesTheIdWithoutSayingWhoStoredIt(): void
    {
        $e = McpException::toolResultNotFound('abc');

        static::assertSame('Tool result "abc" not found. It may have expired or been stored by another caller.', $e->uri);
    }

    public function testAppPromptFailedPassesTheErrorOfTheAppToTheClient(): void
    {
        static::assertSame('ERP unreachable', McpException::appPromptFailed('my-app-context', 'ERP unreachable')->getMessage());
        static::assertSame('App prompt "my-app-context" failed.', McpException::appPromptFailed('my-app-context', '')->getMessage());
        static::assertSame('App prompt "my-app-context" failed.', McpException::appPromptFailed('my-app-context', null)->getMessage());
    }

    public function testInvalidAppPromptMessagesNameThePromptAndPreserveTheFormattingError(): void
    {
        static::assertEquals(
            new PromptGetException('App prompt "my-app-context" returned invalid messages: Invalid message format at index 0.'),
            McpException::appPromptMessagesInvalid('my-app-context', 'Invalid message format at index 0.'),
        );
    }

    public function testAppResourceFailedPassesTheErrorOfTheAppToTheClient(): void
    {
        static::assertSame('ERP unreachable', McpException::appResourceFailed('my-app-stats', 'ERP unreachable')->getMessage());
        static::assertSame('App resource "my-app-stats" failed.', McpException::appResourceFailed('my-app-stats', '')->getMessage());
        static::assertSame('App resource "my-app-stats" failed.', McpException::appResourceFailed('my-app-stats', null)->getMessage());
    }

    public function testAnUnusableAppPromptResponseNamesThePrompt(): void
    {
        static::assertEquals(
            new PromptGetException('App prompt "my-app-context" returned a response that is not valid JSON.'),
            McpException::appPromptResponseNotJson('my-app-context'),
        );
        static::assertEquals(
            new PromptGetException('App prompt "my-app-context" must respond with a JSON array of messages.'),
            McpException::appPromptResponseNotAMessageList('my-app-context'),
        );
    }

    public function testAnUnusableAppResourceResponseNamesTheResource(): void
    {
        static::assertEquals(
            new ResourceReadException('App resource "my-app-stats" returned a response that is not valid JSON.'),
            McpException::appResourceResponseNotJson('my-app-stats'),
        );
        static::assertEquals(
            new ResourceReadException('App resource "my-app-stats" must respond with a JSON object holding a "text" or "blob" string.'),
            McpException::appResourceResponseInvalid('my-app-stats'),
        );
    }
}
