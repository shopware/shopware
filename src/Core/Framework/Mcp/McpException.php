<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Exception\ResourceNotFoundException;
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

    public static function toolResultNotFound(string $id): ResourceNotFoundException
    {
        return new ResourceNotFoundException(\sprintf('Tool result "%s" not found or belongs to a different session.', $id));
    }
}
