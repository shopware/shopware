<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\DTO\ConsentState;
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
            'SELECT name, identifier, state, actor_id, updated_at FROM consent_state'
        );

        return array_map(
            fn (array $row) => new ConsentStateRecord(
                $row['name'],
                $row['identifier'],
                ConsentStatus::from($row['state']),
                $row['actor_id'],
                $row['updated_at']
            ),
            $result
        );
    }

    public function updateConsentState(
        ConsentDefinition $consent,
        string $scopeIdentifier,
        ConsentStatus $state,
        string $actorId
    ): ConsentState {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement('
        INSERT INTO consent_state (id, name, identifier, state, actor_id, updated_at)
        VALUES (:id, :consentName, :identifier, :state, :actorId, :updatedAt)
        ON DUPLICATE KEY UPDATE
            state = :state,
            actor_id = :actorId,
            updated_at = :updatedAt
        ', [
            'id' => Uuid::randomBytes(),
            'consentName' => $consent->getName(),
            'identifier' => $scopeIdentifier,
            'state' => $state->value,
            'actorId' => $actorId,
            'updatedAt' => $now,
        ], ['id' => 'binary']);

        return new ConsentState(
            $consent->getName(),
            $consent->getScopeName(),
            $scopeIdentifier,
            $state,
            $actorId,
            $now
        );
    }
}
