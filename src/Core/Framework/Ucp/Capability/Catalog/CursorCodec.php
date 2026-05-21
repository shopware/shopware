<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Catalog;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Opaque, tamper-resistant cursor codec for UCP catalog pagination.
 *
 * The codec serialises an internal pagination state into a single string the
 * client must echo back unchanged. Tamper-resistance is enforced via a
 * detached HMAC-SHA256 signature derived from `APP_SECRET`, so a client cannot
 * forge a cursor that points elsewhere in the catalogue without the platform's
 * cooperation.
 *
 * Encoded form:
 *
 *   base64url(payload_json) . '.' . base64url(hmac_sha256(payload_json, key))
 *
 * Two pagination modes are supported:
 *
 *  - `page` — used whenever the request includes a textual `query`, because
 *    `ProductSearchRoute` returns scored results and we cannot resume by
 *    `id > X`. The cursor carries the next page number.
 *  - `after` — used for non-search browses. The cursor carries the last seen
 *    product id; the controller adds a `id > after` range filter and sorts
 *    deterministically by `id ASC`.
 *
 * The query fingerprint `q` ties a cursor to its originating query so a
 * cursor cannot be replayed against a different query and surface unexpected
 * items.
 *
 * @internal
 */
#[Package('framework')]
final class CursorCodec
{
    public const MODE_PAGE = 'page';
    public const MODE_AFTER = 'after';

    private const VERSION = 1;

    /**
     * Cursors expire after 15 minutes — long enough for human pagination, short enough that a stolen cursor is not interesting.
     */
    private const MAX_AGE_SECONDS = 900;

    /**
     * Encode an internal cursor state. Throws if the state cannot be serialised
     * (which would indicate a programming error in the controller, never a
     * client error).
     *
     * @param array{
     *     mode: self::MODE_*,
     *     page?: int,
     *     after?: string|null,
     *     q?: string,
     * } $state
     */
    public function encode(array $state): string
    {
        $payload = [
            'v' => self::VERSION,
            'mode' => $state['mode'],
            'page' => $state['page'] ?? null,
            'after' => $state['after'] ?? null,
            'q' => $state['q'] ?? '',
            'iat' => time(),
        ];

        $json = json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw UcpException::invalidArgument('Catalog cursor payload could not be JSON-encoded');
        }

        $sig = hash_hmac('sha256', $json, $this->deriveKey(), true);

        return $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($sig);
    }

    /**
     * Decode + verify a cursor. Throws {@see UcpException::invalidCursor()}
     * for any tamper / shape / replay violation. Successful decode returns
     * the same shape that {@see encode()} accepts (without `iat`).
     *
     * @return array{
     *     mode: self::MODE_*,
     *     page: ?int,
     *     after: ?string,
     *     q: string,
     * }
     */
    public function decode(string $cursor, string $expectedQueryFingerprint): array
    {
        $parts = explode('.', $cursor);
        if (\count($parts) !== 2) {
            throw UcpException::invalidCursor('cursor must be `payload.signature`');
        }

        [$payloadB64, $sigB64] = $parts;
        $json = $this->base64UrlDecode($payloadB64);
        $sig = $this->base64UrlDecode($sigB64);
        if ($json === '' || $sig === '') {
            throw UcpException::invalidCursor('cursor segments empty');
        }

        $expected = hash_hmac('sha256', $json, $this->deriveKey(), true);
        if (!hash_equals($expected, $sig)) {
            throw UcpException::invalidCursor('cursor signature mismatch');
        }

        $payload = json_decode($json, true);
        if (!\is_array($payload)) {
            throw UcpException::invalidCursor('cursor payload not JSON');
        }

        if (($payload['v'] ?? null) !== self::VERSION) {
            throw UcpException::invalidCursor('unsupported cursor version');
        }

        $mode = $payload['mode'] ?? null;
        if ($mode !== self::MODE_PAGE && $mode !== self::MODE_AFTER) {
            throw UcpException::invalidCursor('unsupported cursor mode');
        }

        $iat = $payload['iat'] ?? null;
        if (!\is_int($iat) || $iat <= 0 || $iat + self::MAX_AGE_SECONDS < time()) {
            throw UcpException::invalidCursor('cursor expired');
        }

        $q = $payload['q'] ?? '';
        if (!\is_string($q) || !hash_equals($expectedQueryFingerprint, $q)) {
            // The cursor is valid in itself but was issued for a different
            // query/filter combination. Refusing the cursor here prevents a
            // client from "carrying over" a cursor between unrelated queries
            // and getting a confusingly mixed result page.
            throw UcpException::invalidCursor('cursor query fingerprint mismatch');
        }

        $page = $payload['page'] ?? null;
        $after = $payload['after'] ?? null;

        return [
            'mode' => $mode,
            'page' => \is_int($page) ? $page : null,
            'after' => \is_string($after) ? $after : null,
            'q' => $q,
        ];
    }

    /**
     * Build a stable fingerprint over the parts of the request that change the
     * result set. Used to bind a cursor to its originating query.
     *
     * @param array<string, mixed> $filters
     */
    public function fingerprint(string $query, array $filters): string
    {
        $material = json_encode([
            'query' => $query,
            'filters' => $this->normaliseFilters($filters),
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        // sha256 (not Hasher::hash) intentional: query-fingerprint binding is
        // a security control to keep a cursor from being replayed against a
        // different query; we want collision resistance, not just stability.
        // @phpstan-ignore-next-line shopware.hasher
        return substr(hash('sha256', $material === false ? $query : $material), 0, 16);
    }

    /**
     * Recursively sort array keys so logically equivalent filter objects
     * produce identical fingerprints regardless of client field order.
     *
     * @param array<int|string, mixed> $value
     *
     * @return array<int|string, mixed>
     */
    private function normaliseFilters(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (\is_array($v)) {
                $value[$k] = $this->normaliseFilters($v);
            }
        }

        return $value;
    }

    private function deriveKey(): string
    {
        // Bind the derivation context to this codec so the same APP_SECRET
        // cannot accidentally produce a cursor that validates against a
        // different UCP HMAC use-case (consent ticket, embedded session, …).
        $secret = (string) EnvironmentHelper::getVariable('APP_SECRET');

        // sha256 (not Hasher::hash) intentional: HMAC key derivation for cursor
        // integrity — must be cryptographically secure, not just stable.
        // @phpstan-ignore-next-line shopware.hasher
        return hash('sha256', $secret . '|ucp-catalog-cursor-v1', true);
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): string
    {
        $padded = strtr($encoded, '-_', '+/');
        $remainder = \strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return $decoded === false ? '' : $decoded;
    }
}
