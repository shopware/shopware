<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * A tool failure with a stable code, so every output format can express it: MCP `isError`, an error
 * object, or a status code. The message is for the model and the user; the code is for clients.
 */
#[Package('framework')]
final readonly class McpToolError
{
    public const TOOL_ERROR = 'tool_error';

    public const MISSING_PRIVILEGE = 'missing_privilege';

    public const INVALID_ARGUMENTS = 'invalid_arguments';

    public const NOT_FOUND = 'not_found';

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $message,
        public string $code = self::TOOL_ERROR,
        public array $details = [],
    ) {
    }
}
