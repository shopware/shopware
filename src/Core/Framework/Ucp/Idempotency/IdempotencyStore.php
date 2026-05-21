<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Idempotency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Idempotency-Key store backed by `ucp_idempotency_key`. Implements the
 * pattern from UCP overview.md §"Idempotency" with a two-phase commit:
 *
 *   1. {@see claim()}  — INSERT a row in state `pending` BEFORE the controller
 *                        runs. Returns either:
 *                          * 'fresh'      — we claimed the key, proceed
 *                          * 'replay'     — same key + same body, return cached
 *                          * 409 throw    — same key + different body
 *   2. {@see commit()} — UPDATE the row to `committed` once the controller's
 *                        response is known.
 *
 * This closes the TOCTOU race that the previous lookup → run → store flow
 * had: two concurrent retries with the same key both saw "no row yet" and
 * both ran the business logic (e.g. placed the order twice). With the
 * pre-insert claim, the loser is rejected on the UNIQUE constraint and
 * sees the still-`pending` row become `committed` once the winner finishes.
 *
 * Keys live for 48h (UCP signatures.md §"Idempotency"); expired rows are
 * purged opportunistically on insert.
 *
 * @internal
 */
#[Package('framework')]
class IdempotencyStore
{
    public const RETENTION_HOURS = 48;

    public const RESULT_FRESH = 'fresh';
    public const RESULT_REPLAY = 'replay';
    public const RESULT_IN_FLIGHT = 'in_flight';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Try to claim the (sales_channel, key) pair for a write. Returns:
     *
     *   - status 'fresh'   + null         → controller should proceed
     *   - status 'replay'  + cached array → return the cached response
     *   - status 'in_flight' + null       → another request is still running
     *                                       this key (rare, treated as conflict)
     *
     * Throws {@see UcpException::idempotencyKeyConflict()} when the key is
     * reused with a **different** request body.
     *
     * @return array{status: string, cached: array{status:int, headers: array<string,string>, body: string}|null}
     */
    public function claim(
        string $salesChannelId,
        string $idempotencyKey,
        string $routeName,
        string $requestFingerprint
    ): array {
        $now = new \DateTimeImmutable();
        $nowStr = $now->format('Y-m-d H:i:s.v');
        $expiresAt = $now->modify('+' . self::RETENTION_HOURS . ' hours')->format('Y-m-d H:i:s.v');

        // Path 1: try to insert a fresh `pending` row. The UNIQUE(sc, key)
        // index makes this atomic — only one concurrent request can win.
        if ($this->insertPendingClaim($salesChannelId, $idempotencyKey, $routeName, $requestFingerprint, $nowStr, $expiresAt)) {
            return ['status' => self::RESULT_FRESH, 'cached' => null];
        }

        $row = $this->connection->fetchAssociative(
            'SELECT response_status, response_headers, response_body, request_fingerprint, route_name, expires_at
             FROM ucp_idempotency_key
             WHERE sales_channel_id = ? AND idempotency_key = ?
             LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $idempotencyKey]
        );

        if (!\is_array($row)) {
            // Race: row vanished between INSERT and SELECT (cleanup task).
            // Re-claim atomically; never return fresh without a DB row.
            // PHPStan can't see the catch on UniqueConstraintViolationException,
            // so it thinks insertPendingClaim() is always true — but the catch
            // is the whole point of the defensive re-claim.
            // @phpstan-ignore-next-line if.alwaysFalse
            if ($this->insertPendingClaim($salesChannelId, $idempotencyKey, $routeName, $requestFingerprint, $nowStr, $expiresAt)) {
                return ['status' => self::RESULT_FRESH, 'cached' => null];
            }

            return ['status' => self::RESULT_IN_FLIGHT, 'cached' => null];
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            // The existing row has expired; delete it and immediately re-claim.
            $this->connection->executeStatement(
                'DELETE FROM ucp_idempotency_key WHERE sales_channel_id = ? AND idempotency_key = ?',
                [Uuid::fromHexToBytes($salesChannelId), $idempotencyKey]
            );

            // Same defensive re-claim as above; see note on if.alwaysFalse.
            // @phpstan-ignore-next-line if.alwaysFalse
            if ($this->insertPendingClaim($salesChannelId, $idempotencyKey, $routeName, $requestFingerprint, $nowStr, $expiresAt)) {
                return ['status' => self::RESULT_FRESH, 'cached' => null];
            }

            return ['status' => self::RESULT_IN_FLIGHT, 'cached' => null];
        }

        // Fingerprint mismatch → reused key with a different body. Per UCP
        // overview.md this is a hard 409.
        if (!hash_equals((string) $row['request_fingerprint'], $requestFingerprint)) {
            throw UcpException::idempotencyKeyConflict($idempotencyKey);
        }
        if (!hash_equals((string) $row['route_name'], $routeName)) {
            throw UcpException::idempotencyKeyConflict($idempotencyKey);
        }

        if ((int) $row['response_status'] === 0) {
            // First call is still in flight — concurrent retry. Tell the
            // caller to bail with 409 (instead of running the controller a
            // second time).
            return ['status' => self::RESULT_IN_FLIGHT, 'cached' => null];
        }

        /** @var array<string, string> $headers */
        $headers = json_decode((string) $row['response_headers'], true, flags: \JSON_THROW_ON_ERROR);

        return [
            'status' => self::RESULT_REPLAY,
            'cached' => [
                'status' => (int) $row['response_status'],
                'headers' => $headers,
                'body' => (string) $row['response_body'],
            ],
        ];
    }

    /**
     * Persist the final response onto the claimed row. Called after the
     * controller has produced a response.
     */
    public function commit(
        string $salesChannelId,
        string $idempotencyKey,
        Response $response
    ): void {
        $this->connection->update(
            'ucp_idempotency_key',
            [
                'response_status' => $response->getStatusCode(),
                'response_headers' => json_encode($this->captureResponseHeaders($response), \JSON_THROW_ON_ERROR),
                'response_body' => (string) $response->getContent(),
            ],
            [
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    /**
     * Releases a pending claim when the controller errored out before a
     * response could be cached. Without this the second retry would see
     * status=0 forever.
     */
    public function abort(string $salesChannelId, string $idempotencyKey): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ucp_idempotency_key WHERE sales_channel_id = ? AND idempotency_key = ? AND response_status = 0',
            [Uuid::fromHexToBytes($salesChannelId), $idempotencyKey]
        );
    }

    /**
     * Computes a stable fingerprint over (route | method | path | sorted query | body).
     * Includes the route name to disambiguate different operations that
     * happen to share the same path (extremely rare but spec-correct).
     */
    public static function computeFingerprint(
        string $routeName,
        string $method,
        string $path,
        string $query,
        string $body
    ): string {
        parse_str($query, $params);
        ksort($params);

        // sha256 (not Hasher::hash) intentional: the fingerprint is used to
        // detect that the same Idempotency-Key was reused against a *different*
        // body. We need cryptographic collision resistance — otherwise a
        // malicious caller could craft two bodies that share a Hasher digest
        // and replay a stale response.
        // @phpstan-ignore-next-line shopware.hasher
        return hash(
            'sha256',
            $routeName . "\n"
            . strtoupper($method) . "\n"
            . $path . "\n"
            . http_build_query($params) . "\n"
            . $body
        );
    }

    /**
     * @return array<string, string>
     */
    private function captureResponseHeaders(Response $response): array
    {
        $allow = ['content-type', 'cache-control', 'etag', 'vary', 'sw-language-id'];
        $captured = [];
        foreach ($allow as $name) {
            $value = $response->headers->get($name);
            if (\is_string($value) && $value !== '') {
                $captured[$name] = $value;
            }
        }

        return $captured;
    }

    private function insertPendingClaim(
        string $salesChannelId,
        string $idempotencyKey,
        string $routeName,
        string $requestFingerprint,
        string $nowStr,
        string $expiresAt
    ): bool {
        try {
            $this->connection->insert('ucp_idempotency_key', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'idempotency_key' => $idempotencyKey,
                'route_name' => $routeName,
                'request_fingerprint' => $requestFingerprint,
                // `response_status=0` is a sentinel for "pending"; commit() sets it.
                'response_status' => 0,
                'response_headers' => '{}',
                'response_body' => '',
                'created_at' => $nowStr,
                'expires_at' => $expiresAt,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
