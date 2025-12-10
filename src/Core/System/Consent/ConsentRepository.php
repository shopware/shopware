<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\Consent;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateHistoryItem;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $name, ConsentScope $scope): void
    {
        try {
            $this->connection->insert('consent', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'scope' => $scope->value,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw ConsentException::alreadyExists($name);
        }
    }

    /**
     * @return array<Consent>
     */
    public function fetchAllConsents(): array
    {
        $result = $this->connection->fetchAllAssociative('SELECT * FROM consent');

        return array_map(function (array $row) {
            $createdAt = \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['created_at']);
            $updatedAt = $row['updated_at'] ? \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['updated_at']) : null;

            if ($createdAt === false || $updatedAt === false) {
                throw ConsentException::invalidConsent();
            }

            return new Consent(
                id: Uuid::fromBytesToHex($row['id']),
                name: $row['name'],
                scope: ConsentScope::from($row['scope']),
                createdAt: $createdAt,
                updatedAt: $updatedAt,
            );
        }, $result);
    }

    /**
     * @return list<ConsentState>
     */
    public function fetchAllConsentStates(): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT consent_state.actor_id, identifier, state, consent.name, consent.scope FROM consent_state LEFT JOIN consent ON consent.id = consent_state.consent_id'
        );

        return array_map(
            fn (array $row) => new ConsentState(
                $row['name'],
                ConsentScope::from($row['scope']),
                $row['identifier'] ? Uuid::fromBytesToHex($row['identifier']) : null,
                ConsentStatus::from($row['state']),
                Uuid::fromBytesToHex($row['actor_id'])
            ),
            $result
        );
    }

    public function updateConsentState(
        Consent $consent,
        ?string $identifier,
        ConsentStatus $state,
        string $actorId
    ): void {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM consent_state WHERE consent_id = :consentId AND identifier <=> :identifier',
            [
                'consentId' => Uuid::fromHexToBytes($consent->id),
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
                'consent_id' => Uuid::fromHexToBytes($consent->id),
                'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
                'state' => $state->value,
                'actor_id' => Uuid::fromHexToBytes($actorId),
                'created_at' => $now,
            ]);
        }

        $this->connection->insert('consent_state_history', [
            'id' => Uuid::randomBytes(),
            'consent_id' => Uuid::fromHexToBytes($consent->id),
            'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
            'state' => $state->value,
            'actor_id' => Uuid::fromHexToBytes($actorId),
            'created_at' => $now,
        ]);
    }

    /**
     * @return list<ConsentStateHistoryItem>
     */
    public function getHistory(string $consentId, ?string $identifier): array
    {
        $result = $this->connection->fetchAllAssociative(
            'SELECT state, actor_id, created_at
             FROM consent_state_history
             WHERE consent_id = :consentId AND identifier <=> :identifier
             ORDER BY created_at DESC',
            [
                'consentId' => Uuid::fromHexToBytes($consentId),
                'identifier' => $identifier ? Uuid::fromHexToBytes($identifier) : null,
            ]
        );

        return array_map(
            function (array $row) {
                $createdAt = \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_TIME_FORMAT, $row['created_at']);

                if ($createdAt === false) {
                    throw ConsentException::invalidConsent();
                }

                return new ConsentStateHistoryItem(
                    ConsentStatus::from($row['state']),
                    Uuid::fromBytesToHex($row['actor_id']),
                    Uuid::fromBytesToHex($row['actor_id']),
                    $createdAt
                );
            },
            $result,
        );
    }
}
