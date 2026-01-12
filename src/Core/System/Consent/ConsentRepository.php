<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
                $row['identifier'],
                ConsentStatus::from($row['state']),
                $row['actor_id']
            ),
            $result
        );
    }

    public function updateConsentState(
        ConsentDefinition $consent,
        string $scopeIdentifier,
        ConsentStatus $state,
        string $actorId
    ): void {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM consent_state WHERE name = :consentName AND identifier <=> :identifier',
            [
                'consentName' => $consent->getName(),
                'identifier' => $scopeIdentifier,
            ]
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($existing) {
            $this->connection->update(
                'consent_state',
                [
                    'state' => $state->value,
                    'actor_id' => $actorId,
                    'updated_at' => $now,
                ],
                ['id' => $existing]
            );
        } else {
            $this->connection->insert('consent_state', [
                'id' => Uuid::randomBytes(),
                'name' => $consent->getName(),
                'identifier' => $scopeIdentifier,
                'state' => $state->value,
                'actor_id' => $actorId,
                'created_at' => $now,
            ]);
        }
    }
}
