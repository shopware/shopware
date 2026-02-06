<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Elasticsearch\Product\CustomFieldSetGatewayTest
 */
#[Package('framework')]
class CustomFieldSetGateway
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string> $setIds
     *
     * @return array<string, list<array{id: string, name: string, type: string}>>
     */
    public function fetchCustomFieldsForSets(array $setIds): array
    {
        /** @var list<array{id: string, name: string, type: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(set_id)) as set_id, LOWER(HEX(id)) AS id, name, type
                FROM custom_field
                WHERE set_id IN (:setIds)
                    AND include_in_search = 1
            SQL,
            ['setIds' => Uuid::fromHexToBytesList($setIds)],
            ['setIds' => ArrayParameterType::STRING]
        );

        /** @var array<string, list<array{id: string, name: string, type: string}>> $customFields */
        $customFields = FetchModeHelper::group($result);

        return $customFields;
    }

    /**
     * @param array<string> $setIds
     * @param array<string> $usedFieldNames
     * @param array<string> $appOwnedSetIds
     *
     * @return array<string, list<array{id: string, name: string, type: string}>>
     */
    public function fetchIndexableCustomFieldsForSets(array $setIds, array $usedFieldNames = [], array $appOwnedSetIds = []): array
    {
        if ($setIds === []) {
            return [];
        }

        $params = ['setIds' => Uuid::fromHexToBytesList($setIds)];
        $types = ['setIds' => ArrayParameterType::STRING];

        $conditions = ['cf.include_in_search = 1'];

        if ($appOwnedSetIds !== []) {
            $conditions[] = 'cf.set_id IN (:appOwnedSetIds)';
            $params['appOwnedSetIds'] = Uuid::fromHexToBytesList($appOwnedSetIds);
            $types['appOwnedSetIds'] = ArrayParameterType::STRING;
        }

        if ($usedFieldNames !== []) {
            $conditions[] = 'cf.name IN (:usedFieldNames)';
            $params['usedFieldNames'] = $usedFieldNames;
            $types['usedFieldNames'] = ArrayParameterType::STRING;
        }

        $conditionSql = implode(' OR ', $conditions);

        /** @var list<array{id: string, name: string, type: string}> $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(cf.set_id)) as set_id, LOWER(HEX(cf.id)) AS id, cf.name, cf.type
                FROM custom_field cf
                WHERE cf.set_id IN (:setIds)
                    AND cf.active = 1
                    AND (' . $conditionSql . ')',
            $params,
            $types
        );

        /** @var array<string, list<array{id: string, name: string, type: string}>> $customFields */
        $customFields = FetchModeHelper::group($result);

        return $customFields;
    }

    /**
     * @param array<string> $candidateNames
     *
     * @return array<string>
     */
    public function fetchCustomFieldNamesUsedInProductSorting(array $candidateNames = []): array
    {
        $params = ['fields' => 'customFields.%'];
        $types = [];

        $candidateCondition = '';
        if (\count($candidateNames) > 0) {
            $candidateCondition = 'AND REPLACE(jt.field_value, \'customFields.\', \'\') IN (:candidateNames)';
            $params['candidateNames'] = $candidateNames;
            $types['candidateNames'] = ArrayParameterType::STRING;
        }

        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT
                    REPLACE(jt.field_value, 'customFields.', '') as field_name
                FROM product_sorting
                CROSS JOIN JSON_TABLE(
                    fields,
                    '$[*]' COLUMNS (
                        field_value VARCHAR(255) PATH '$.field'
                    )
                ) AS jt
                WHERE active = 1
                    AND locked = 0
                    AND jt.field_value LIKE :fields
                SQL . ' ' . $candidateCondition,
            $params,
            $types
        );
    }

    /**
     * @param array<string> $candidateNames
     *
     * @return array<string>
     */
    public function fetchCustomFieldNamesUsedInProductStream(array $candidateNames = []): array
    {
        $params = ['field' => 'customFields.%'];
        $types = [];

        $candidateCondition = '';
        if (\count($candidateNames) > 0) {
            $candidateCondition = 'AND REPLACE(field, \'customFields.\', \'\') IN (:candidateNames)';
            $params['candidateNames'] = $candidateNames;
            $types['candidateNames'] = ArrayParameterType::STRING;
        }

        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT REPLACE(field, \'customFields.\', \'\') FROM product_stream_filter WHERE field LIKE :field ' . $candidateCondition,
            $params,
            $types
        );
    }

    /**
     * @param array<string> $setIds
     *
     * @return array<string>
     */
    public function fetchCustomFieldNamesBySetIds(array $setIds): array
    {
        if (\count($setIds) === 0) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT name FROM custom_field WHERE set_id IN (:setIds) AND active = 1',
            ['setIds' => Uuid::fromHexToBytesList($setIds)],
            ['setIds' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param array<string> $setIds
     *
     * @return array<string>
     */
    public function fetchAppOwnedFieldSetIds(array $setIds): array
    {
        if ($setIds === []) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM custom_field_set WHERE id IN (:ids) AND app_id IS NOT NULL',
            ['ids' => Uuid::fromHexToBytesList($setIds)],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    public function isAppOwnedFieldSet(string $setId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM custom_field_set WHERE id = :id AND app_id IS NOT NULL',
            ['id' => Uuid::fromHexToBytes($setId)]
        );
    }

    /**
     * @param array<string> $customFieldIds
     *
     * @return array<string, string>
     */
    public function fetchFieldSetIds(array $customFieldIds): array
    {
        /** @var array<string, string> $result */
        $result = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), LOWER(HEX(set_id)) FROM custom_field WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($customFieldIds)],
            ['ids' => ArrayParameterType::STRING]
        );

        return $result;
    }

    /**
     * @param array<string> $fieldSetIds
     *
     * @return array<string, list<string>>
     */
    public function fetchFieldSetEntityMappings(array $fieldSetIds): array
    {
        /** @var list<array{set_id: string, entity_name: string}> $fieldSets */
        $fieldSets = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(custom_field_set.id)) AS set_id, entity_name
                FROM custom_field_set
                LEFT JOIN custom_field_set_relation ON custom_field_set.id = custom_field_set_relation.set_id
                WHERE custom_field_set.id IN (:ids)
            SQL,
            ['ids' => Uuid::fromHexToBytesList($fieldSetIds)],
            ['ids' => ArrayParameterType::STRING]
        );

        return FetchModeHelper::group($fieldSets, static fn (array $row): string => (string) $row['entity_name']);
    }

    /**
     * @return array<string>
     */
    public function fetchLanguageIds(): array
    {
        /** @var list<string> $languageIds */
        $languageIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM language');

        return $languageIds;
    }
}
