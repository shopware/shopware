<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * A signed reference to a stored tool result, as returned by
 * {@see \Shopware\Core\Framework\Mcp\ToolResultCacheStorage::storeFor()}.
 */
#[Package('framework')]
final readonly class McpToolResultPointer
{
    public const URI_PREFIX = 'shopware://tool-result/';

    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public function uri(): string
    {
        return self::URI_PREFIX . $this->token;
    }
}
