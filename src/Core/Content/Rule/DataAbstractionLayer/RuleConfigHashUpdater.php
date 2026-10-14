<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\RuleConfigurationNormalizer;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Rule\DataAbstractionLayer\RuleConfigHashUpdaterTest
 *
 * @phpstan-import-type ConditionRow from RuleConfigurationNormalizer
 */
#[Package('fundamentals@after-sales')]
class RuleConfigHashUpdater
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RuleConfigurationNormalizer $normalizer,
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function update(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        /** @var array<string, list<ConditionRow>> $conditionsByRule */
        $conditionsByRule = FetchModeHelper::group($this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(rule_id)) AS array_key,
                LOWER(HEX(id)) AS id,
                LOWER(HEX(parent_id)) AS parent_id,
                type,
                value,
                LOWER(HEX(script_id)) AS script_id
            FROM rule_condition
            WHERE rule_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        ));

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET config_hash = :configHash WHERE id = :id')
        );

        foreach ($ids as $id) {
            $update->execute([
                'configHash' => $this->normalizer->checksum($conditionsByRule[$id] ?? []),
                'id' => Uuid::fromHexToBytes($id),
            ]);
        }
    }
}
