<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('inventory')]
class ElasticsearchIndexingUtils
{
    public const TEXT_MAX_LENGTH = 32766;

    /**
     * @var array<string, array<string, string>>
     */
    private array $customFieldsTypes = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @throws Exception
     *
     * @return array<string, string>
     */
    public function getCustomFieldTypes(string $entity, Context $context): array
    {
        if (\array_key_exists($entity, $this->customFieldsTypes)) {
            return $this->customFieldsTypes[$entity];
        }

        $mappingKey = \sprintf('elasticsearch.%s.custom_fields_mapping', $entity);
        $customFieldsMapping = $this->parameterBag->has($mappingKey) ? $this->parameterBag->get($mappingKey) : [];

        $usedFieldNames = array_unique(array_merge(
            $this->fetchCustomFieldNamesUsedInProductSorting(),
            $this->fetchCustomFieldNamesUsedInProductStream()
        ));

        /** @var array<string, string> $mappings */
        $mappings = $this->connection->fetchAllKeyValue(
            '
SELECT
    custom_field.name,
    custom_field.type
FROM custom_field_set_relation
    INNER JOIN custom_field ON(custom_field.set_id = custom_field_set_relation.set_id)
    INNER JOIN custom_field_set ON(custom_field_set.id = custom_field.set_id)
WHERE custom_field_set_relation.entity_name = :entity
    AND custom_field.active = 1
    AND (custom_field.name IN (:fields) OR custom_field_set.app_id IS NOT NULL OR custom_field.include_in_search = 1)',
            ['entity' => $entity, 'fields' => $usedFieldNames],
            ['fields' => ArrayParameterType::STRING]
        ) + $customFieldsMapping;

        $event = new ElasticsearchCustomFieldsMappingEvent($entity, $mappings, $context);

        $this->eventDispatcher->dispatch($event);

        $this->customFieldsTypes[$entity] = $event->getMappings();

        return $this->customFieldsTypes[$entity];
    }

    /**
     * @description strip html tags from text and truncate to 32766 characters
     */
    public static function stripText(string $text): string
    {
        // Remove all html elements to save up space
        $text = strip_tags($text);

        if (mb_strlen($text) >= self::TEXT_MAX_LENGTH) {
            return mb_substr($text, 0, self::TEXT_MAX_LENGTH);
        }

        return $text;
    }

    /**
     * @param array<string, string|null> $record
     *
     * @throws \JsonException
     *
     * @return array<mixed>
     */
    public static function parseJson(array $record, string $field): array
    {
        if (!\array_key_exists($field, $record)) {
            return [];
        }

        return json_decode($record[$field] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string>
     */
    private function fetchCustomFieldNamesUsedInProductSorting(): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT fields FROM product_sorting WHERE fields LIKE :pattern',
            ['pattern' => '%customFields.%']
        );

        return $this->extractCustomFieldNames($rows);
    }

    /**
     * @return array<string>
     */
    private function fetchCustomFieldNamesUsedInProductStream(): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT api_filter FROM product_stream WHERE api_filter LIKE :pattern',
            ['pattern' => '%customFields.%']
        );

        return $this->extractCustomFieldNames($rows);
    }

    /**
     * @param list<string> $rows
     *
     * @return list<string>
     */
    private function extractCustomFieldNames(array $rows): array
    {
        $customFieldNames = [];
        $prefixLength = \strlen('customFields.');

        foreach ($rows as $row) {
            try {
                $data = json_decode((string) $row, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($data)) {
                continue;
            }

            array_walk_recursive($data, static function (mixed $value, string|int $key) use (&$customFieldNames, $prefixLength): void {
                if ($key !== 'field' || !\is_string($value)) {
                    return;
                }

                $pos = strpos($value, 'customFields.');
                if ($pos !== false) {
                    $customFieldNames[substr($value, $pos + $prefixLength)] = true;
                }
            });
        }

        return array_keys($customFieldNames);
    }
}
