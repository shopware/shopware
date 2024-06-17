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
#[Package('core')]
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
            SQL,
            ['setIds' => Uuid::fromHexToBytesList($setIds)],
            ['setIds' => ArrayParameterType::STRING]
        );

        /** @var array<string, list<array{id: string, name: string, type: string}>> $customFields */
        $customFields = FetchModeHelper::group($result);

        return $customFields;
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
        /** @var array<string> $languageIds */
        $languageIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM language');

        return $languageIds;
    }
}
