<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

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
#[Package('buyers-experience')]
class ElasticsearchIndexingUtils
{
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

        $mappingKey = sprintf('elasticsearch.%s.custom_fields_mapping', $entity);
        $customFieldsMapping = $this->parameterBag->has($mappingKey) ? $this->parameterBag->get($mappingKey) : [];

        /** @var array<string, string> $mappings */
        $mappings = $this->connection->fetchAllKeyValue('
SELECT
    custom_field.`name`,
    custom_field.type
FROM custom_field_set_relation
    INNER JOIN custom_field ON(custom_field.set_id = custom_field_set_relation.set_id)
WHERE custom_field_set_relation.entity_name = :entity
', ['entity' => $entity]) + $customFieldsMapping;

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

        if (mb_strlen($text) >= 32766) {
            return mb_substr($text, 0, 32766);
        }

        return $text;
    }

    /**
     * @param array<string, string> $record
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
}
