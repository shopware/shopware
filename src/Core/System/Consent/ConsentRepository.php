<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\ConsentStateLogRecord;
use Shopware\Core\System\Consent\DTO\ConsentStateRecord;

/**
 * @internal
 *
 * @codeCoverageIgnore integration tested with \Shopware\Tests\Integration\Core\System\Consent\ConsentRepositoryTest
 */
#[Package('data-services')]
class ConsentRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<ConsentStateRecord>
     */
    public function fetchAllConsentStates(): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT name, identifier, state, consent_state.actor_id FROM consent_state'
        );

        return array_map(
            fn (array $row) => new ConsentStateRecord(
                $row['name'],
                $row['identifier'] ? Uuid::fromBytesToHex($row['identifier']) : null,
                ConsentStatus::from($row['state']),
                Uuid::fromBytesToHex($row['actor_id'])
            ),
            $result
        );
    }

    public function updateConsentState(
        ConsentDefinition $consent,
        ?string $identifier,
        ConsentStatus $state,
        string $actorId
    ): void {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM consent_state WHERE name = :consentName AND identifier <=> :identifier',
            [
                'consentName' => $consent->getName(),
                'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
            ]
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($existing) {
            $this->connection->update(
                'consent_state',
                [
                    'state' => $state->value,
                    'actor_id' => Uuid::fromHexToBytes($actorId),
                    'updated_at' => $now,
                ],
                ['id' => $existing]
            );
        } else {
            $this->connection->insert('consent_state', [
                'id' => Uuid::randomBytes(),
                'name' => $consent->getName(),
                'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
                'state' => $state->value,
                'actor_id' => Uuid::fromHexToBytes($actorId),
                'created_at' => $now,
            ]);
        }

        $this->connection->insert('consent_log', [
            'id' => Uuid::randomBytes(),
            'name' => $consent->getName(),
            'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
            'state' => $state->value,
            'actor_id' => Uuid::fromHexToBytes($actorId),
            'created_at' => $now,
        ]);
    }

    /**
     * @return list<ConsentStateLogRecord>
     */
    public function getHistory(string $consentName, ?string $identifier): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT state, actor_id, created_at
             FROM consent_log
             WHERE name = :name AND identifier <=> :identifier
             ORDER BY created_at DESC',
            [
                'name' => $consentName,
                'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
            ]
        );

        return array_map(
            function (array $row) use ($identifier) {
                $createdAt = \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['created_at']);

                if ($createdAt === false) {
                    throw ConsentException::invalidConsent();
                }

                return new ConsentStateLogRecord(
                    ConsentStatus::from($row['state']),
                    $identifier,
                    Uuid::fromBytesToHex($row['actor_id']),
                    $createdAt
                );
            },
            $result,
        );
    }
}
