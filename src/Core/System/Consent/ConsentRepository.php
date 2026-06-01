<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\ConsentStateRecord;
use Shopware\Tests\Integration\Core\System\Consent\ConsentRepositoryTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see ConsentRepositoryTest
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
            'SELECT name, identifier, state, actor, updated_at, revision FROM consent_state'
        );

        return array_map(
            static fn (array $row) => new ConsentStateRecord(
                $row['name'],
                $row['identifier'],
                ConsentStatus::from($row['state']),
                $row['actor'],
                $row['updated_at'],
                $row['revision'] ?? null,
            ),
            $result
        );
    }

    public function updateConsentState(
        ConsentDefinition $consent,
        string $scopeIdentifier,
        ConsentStatus $state,
        string $actorId,
        ?string $revision = null,
    ): void {
        if ($state !== ConsentStatus::ACCEPTED) {
            $revision = null;
        }

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $actor = $this->connection->executeQuery('SELECT username from user WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($actorId),
        ])->fetchOne();

        if ($actor === false) {
            throw ConsentException::cannotResolveActor($actorId);
        }

        $this->connection->executeStatement('
        INSERT INTO consent_state (id, name, identifier, state, actor, revision, updated_at)
        VALUES (:id, :consentName, :identifier, :insertState, :actor, :revision, :updatedAt)
        ON DUPLICATE KEY UPDATE
            state = CASE WHEN state = "declined" AND :state = "revoked" THEN "declined" ELSE :state END,
            actor = :actor,
            revision = :revision,
            updated_at = :updatedAt
        ', [
            'id' => Uuid::randomBytes(),
            'consentName' => $consent->getName(),
            'identifier' => $scopeIdentifier,
            'insertState' => $state === ConsentStatus::REVOKED ? ConsentStatus::DECLINED->value : $state->value,
            'state' => $state->value,
            'actor' => $actor,
            'revision' => $revision,
            'updatedAt' => $now,
        ], ['id' => 'binary']);
    }
}
