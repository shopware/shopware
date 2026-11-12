<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Exception\PromptGetException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class McpException extends HttpException
{
    private const MCP_UNSUPPORTED_KEY_TYPE = 'MCP__UNSUPPORTED_KEY_TYPE';
    private const MCP_INVALID_CREDENTIALS = 'MCP__INVALID_CREDENTIALS';
    private const MCP_THROTTLED = 'MCP__THROTTLED';
    private const MCP_INVALID_SESSION_ID = 'MCP__INVALID_SESSION_ID';
    private const MCP_OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE = 'MCP__OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE';

    public static function unsupportedKeyType(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_UNSUPPORTED_KEY_TYPE,
            'Only integration or user access keys are supported for MCP authentication.',
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_INVALID_CREDENTIALS,
            'Invalid integration credentials.',
        );
    }

    public static function throttled(int $waitTime, \Throwable $e): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::MCP_THROTTLED,
            'MCP endpoint throttled for {{ seconds }} seconds.',
            ['seconds' => $waitTime],
            $e,
        );
    }

    public static function invalidSessionId(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MCP_INVALID_SESSION_ID,
            'The mcp-session-id header is not a valid session ID.',
        );
    }

    public static function oauthProtectedResourceMetadataUnavailable(string $resource, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::MCP_OAUTH_PROTECTED_RESOURCE_METADATA_UNAVAILABLE,
            'No OAuth protected resource metadata for "{{ resource }}": {{ reason }}',
            ['resource' => $resource, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function toolResultNotFound(string $id): ResourceNotFoundException
    {
        return new ResourceNotFoundException(\sprintf('Tool result "%s" not found. It may have expired or been stored by another caller.', $id));
    }

    public static function appPromptResponseNotJson(string $promptName): PromptGetException
    {
        return new PromptGetException(\sprintf('App prompt "%s" returned a response that is not valid JSON.', $promptName));
    }

    public static function appPromptResponseNotAMessageList(string $promptName): PromptGetException
    {
        return new PromptGetException(\sprintf('App prompt "%s" must respond with a JSON array of messages.', $promptName));
    }

    public static function appPromptMessagesInvalid(string $promptName, string $error): PromptGetException
    {
        return new PromptGetException(\sprintf('App prompt "%s" returned invalid messages: %s', $promptName, $error));
    }

    public static function appPromptFailed(string $promptName, ?string $error): PromptGetException
    {
        return new PromptGetException($error ?: \sprintf('App prompt "%s" failed.', $promptName));
    }

    public static function appResourceResponseNotJson(string $resourceName): ResourceReadException
    {
        return new ResourceReadException(\sprintf('App resource "%s" returned a response that is not valid JSON.', $resourceName));
    }

    public static function appResourceResponseInvalid(string $resourceName): ResourceReadException
    {
        return new ResourceReadException(\sprintf('App resource "%s" must respond with a JSON object holding a "text" or "blob" string.', $resourceName));
    }

    public static function appResourceFailed(string $resourceName, ?string $error): ResourceReadException
    {
        return new ResourceReadException($error ?: \sprintf('App resource "%s" failed.', $resourceName));
    }
}
