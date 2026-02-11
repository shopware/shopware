<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
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
            'SELECT name, identifier, state, actor, updated_at FROM consent_state'
        );

        return array_map(
            fn (array $row) => new ConsentStateRecord(
                $row['name'],
                $row['identifier'],
                ConsentStatus::from($row['state']),
                $row['actor'],
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

        $actor = $this->connection->executeQuery('SELECT username from user WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($actorId),
        ])->fetchOne();

        if ($actor === false) {
            throw ConsentException::cannotResolveActor($actorId);
        }

        $this->connection->executeStatement('
        INSERT INTO consent_state (id, name, identifier, state, actor, updated_at)
        VALUES (:id, :consentName, :identifier, :state, :actor, :updatedAt)
        ON DUPLICATE KEY UPDATE
            state = :state,
            actor = :actor,
            updated_at = :updatedAt
        ', [
            'id' => Uuid::randomBytes(),
            'consentName' => $consent->getName(),
            'identifier' => $scopeIdentifier,
            'state' => $state->value,
            'actor' => $actor,
            'updatedAt' => $now,
        ], ['id' => 'binary']);

        return new ConsentState(
            $consent->getName(),
            $consent->getScopeName(),
            $scopeIdentifier,
            $state,
            $actor,
            $now
        );
    }

    public function getPreviousLoggedState(
        string $name,
        string $identifier,
        \DateTimeImmutable $at,
        ?ConsentStatus $excludedStateAtTimestamp = null,
    ): ?ConsentStatus {
        $timestampCondition = $excludedStateAtTimestamp !== null
            ? <<<SQL
  AND (
      `timestamp` < :at
      OR (`timestamp` = :at AND JSON_UNQUOTE(JSON_EXTRACT(message, '$.action')) != :excludedStateAtTimestamp)
  )
SQL
            :
            'AND `timestamp` <= :at';

        $sql = <<<SQL
SELECT JSON_UNQUOTE(JSON_EXTRACT(message, '$.action')) AS action
FROM consent_log
WHERE consent_name = :name
  AND JSON_UNQUOTE(JSON_EXTRACT(message, '$.identifier')) = :identifier
{$timestampCondition}
ORDER BY `timestamp` DESC, id DESC
LIMIT 1
SQL;

        $parameters = [
            'name' => $name,
            'identifier' => $identifier,
            'at' => $at->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        if ($excludedStateAtTimestamp !== null) {
            $parameters['excludedStateAtTimestamp'] = $excludedStateAtTimestamp->value;
        }

        try {
            $latestAction = $this->connection->fetchOne($sql, $parameters);
        } catch (Exception) {
            return null;
        }

        if (!\is_string($latestAction)) {
            return null;
        }

        return ConsentStatus::tryFrom($latestAction);
    }
}
