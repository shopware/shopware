<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointerSigner;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Persists large tool results in the DB so the model can read them with `resources/read`.
 *
 * {@see self::storeFor()} returns a signed pointer that only the principal who stored the result can
 * read, on any later request and without an MCP session. The session id is still recorded, so rows are
 * also removed when a handshake-era session ends (DELETE /api/_mcp).
 */
#[Package('framework')]
class ToolResultCacheStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly McpToolResultPointerSigner $signer,
    ) {
    }

    /**
     * Stores content for $principal and returns the signed pointer to it.
     *
     * @param string $principal see {@see \Shopware\Core\Framework\Mcp\Result\McpToolResultPrincipal}
     * @param string $sessionId the MCP session of the request, if any, for the cleanup on DELETE
     */
    public function storeFor(string $principal, string $content, string $sessionId = '', string $mimeType = 'application/json'): McpToolResultPointer
    {
        return $this->signer->sign($this->store($sessionId, $content, $mimeType), $principal);
    }

    /**
     * Returns the stored result a pointer token refers to, if the token is valid for $principal.
     *
     * @return array{content: string, mimeType: string}|null
     */
    public function readFor(string $token, string $principal): ?array
    {
        $id = $this->signer->verify($token, $principal);
        if ($id === null) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT `content`, `mime_type` FROM `mcp_tool_result_cache` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );

        return $row === false ? null : $this->toResult($row);
    }

    /**
     * Stores content and returns the hex UUID that identifies it.
     */
    public function store(string $sessionId, string $content, string $mimeType = 'application/json'): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('mcp_tool_result_cache', [
            'id' => $id,
            'session_id' => $sessionId,
            'mime_type' => $mimeType,
            'content' => $content,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * Returns the stored result for a plain $id, as issued before the pointers were signed, if it belongs
     * to $sessionId and is not older than a signed pointer may be, null otherwise. Every signed pointer
     * starts with the plain id, so without the age check this would outlive the pointer's expiry.
     *
     * @return array{content: string, mimeType: string}|null
     */
    public function read(string $id, string $sessionId): ?array
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT `content`, `mime_type` FROM `mcp_tool_result_cache` WHERE `id` = :id AND `session_id` = :sessionId AND `created_at` >= :since',
            [
                'id' => Uuid::fromHexToBytes($id),
                'sessionId' => $sessionId,
                'since' => $this->clock->now()->modify(\sprintf('-%d seconds', McpToolResultPointerSigner::TTL_SECONDS))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );

        return $row === false ? null : $this->toResult($row);
    }

    /**
     * Deletes all cached results for the given session — called on session end.
     */
    public function deleteForSession(string $sessionId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `mcp_tool_result_cache` WHERE `session_id` = :sessionId',
            ['sessionId' => $sessionId],
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{content: string, mimeType: string}
     */
    private function toResult(array $row): array
    {
        return [
            'content' => (string) $row['content'],
            'mimeType' => (string) $row['mime_type'],
        ];
    }
}
