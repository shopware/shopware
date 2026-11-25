<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HmacSigner;

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

    private readonly HmacSigner $signer;

    /**
     * @internal
     */
    public function __construct(
        #[\SensitiveParameter]
        string $secret,
        ClockInterface $clock,
    ) {
        $this->signer = new HmacSigner($secret, $clock);
    }

    /**
     * @param string $id the hex id of the stored result
     */
    public function sign(string $id, string $principal): McpToolResultPointer
    {
        $expiresAt = $this->signer->expiresAt(self::TTL_SECONDS);
        $expiry = (string) $expiresAt->getTimestamp();

        return new McpToolResultPointer(
            $id . '.' . $expiry . '.' . $this->signer->sign(self::signedData($id, $expiry, $principal)),
            $expiresAt,
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

        if (!$this->signer->verify(self::signedData($id, $expiry, $principal), $signature)) {
            return null;
        }

        if ($this->signer->isExpired((int) $expiry)) {
            return null;
        }

        return $id;
    }

    private static function signedData(string $id, string $expiry, string $principal): string
    {
        return 'mcp-tool-result|' . $id . '|' . $expiry . '|' . $principal;
    }
}
