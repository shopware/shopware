<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Enum\CacheScope;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Cache hints (SEP-2549) for the reference resources. The server-wide policy in packages/mcp.php says
 * "do not cache" for `resources/read`, because the same method also reads stored tool results and app
 * resources. A reference resource whose data rarely changes names its own freshness here.
 *
 * Only the 2026-07-28 revision knows the hints. On the handshake revisions the result carries none, so
 * their answers stay unchanged. The scope is always `private`: the resources are read with the caller's
 * credentials, and a shared cache must never hand one caller's answer to another.
 */
#[Package('framework')]
final class McpResourceCacheHint
{
    /**
     * For data that only changes with a deployment or an extension lifecycle change, such as entity
     * definitions, state machines, business events and flow actions.
     */
    public const DEPLOYMENT_TTL_MS = 3_600_000;

    /**
     * For shop configuration an admin edits rarely, such as currencies, languages and sales channels.
     */
    public const SHOP_CONFIGURATION_TTL_MS = 300_000;

    private function __construct()
    {
    }

    /**
     * @param array{uri: string, mimeType: string, text: string} $contents
     */
    public static function read(array $contents, ?RequestContext $context, int $ttlMs): ReadResourceResult
    {
        $hinted = $context !== null && $context->getProtocolVersion()->isModern();

        return new ReadResourceResult(
            [new TextResourceContents($contents['uri'], $contents['mimeType'], $contents['text'])],
            $hinted ? $ttlMs : null,
            $hinted ? CacheScope::Private : null,
        );
    }
}
