<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Signs the pointer to a stored tool result as `<id>.<expiry>.<signature>`. The signature covers the
 * principal that stored the result, so only the same principal can read it back, on any request and
 * on both protocol eras. Unlike a session id, the token does not depend on server-side state.
 *
 * The principal is not part of the token and the token is not derived from the caller's credentials,
 * so a token that leaks from model context or logs is useless to anyone else and expires on its own.
 */
#[Package('framework')]
class McpToolResultPointerSigner
{
    /**
     * Models read a stored result right after the call that produced it, and an hour leaves room for
     * a slow conversation.
     */
    public const TTL_SECONDS = 3600;

    /**
     * @internal
     */
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param string $id the hex id of the stored result
     */
    public function sign(string $id, string $principal): McpToolResultPointer
    {
        $now = $this->clock->now();
        $expiry = (string) ($now->getTimestamp() + self::TTL_SECONDS);

        return new McpToolResultPointer(
            $id . '.' . $expiry . '.' . $this->signature($id, $expiry, $principal),
            $now->setTimestamp((int) $expiry),
        );
    }

    /**
     * @return string|null the hex id of the stored result, or null when the token is malformed,
     *                     expired, or was issued to another principal
     */
    public function verify(string $token, string $principal): ?string
    {
        $parts = explode('.', $token);
        if (\count($parts) !== 3) {
            return null;
        }

        [$id, $expiry, $signature] = $parts;
        if (!ctype_xdigit($id) || \strlen($id) !== 32 || !ctype_digit($expiry)) {
            return null;
        }

        if (!hash_equals($this->signature($id, $expiry, $principal), $signature)) {
            return null;
        }

        if ((int) $expiry < $this->clock->now()->getTimestamp()) {
            return null;
        }

        return $id;
    }

    private function signature(string $id, string $expiry, string $principal): string
    {
        $mac = hash_hmac('sha256', 'mcp-tool-result|' . $id . '|' . $expiry . '|' . $principal, $this->secret, true);

        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
