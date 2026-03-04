<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class McpException extends HttpException
{
    private const MCP_INVALID_ACCESS_KEY = 'MCP__INVALID_ACCESS_KEY';
    private const MCP_INACTIVE_APP = 'MCP__INACTIVE_APP';
    private const MCP_INVALID_SECRET = 'MCP__INVALID_SECRET';
    private const MCP_UNSUPPORTED_KEY_TYPE = 'MCP__UNSUPPORTED_KEY_TYPE';
    private const MCP_THROTTLED = 'MCP__THROTTLED';

    public static function unsupportedKeyType(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_UNSUPPORTED_KEY_TYPE,
            'Only integration access keys are supported for MCP authentication.',
        );
    }

    public static function invalidAccessKey(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_INVALID_ACCESS_KEY,
            'Invalid integration access key.',
        );
    }

    public static function inactiveApp(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_INACTIVE_APP,
            'The app associated with this integration is inactive.',
        );
    }

    public static function invalidSecret(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::MCP_INVALID_SECRET,
            'Invalid secret access key.',
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
}
