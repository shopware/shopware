<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 *  * @codeCoverageIgnore This would be useless as a unit test. It is integration tested here: \Shopware\Tests\Integration\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriberTest
 */
#[Package('core')]
class CustomFieldsUnusedMediaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Connection $connection,
        private DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        $this->findMediaIds($event);
        $this->findMediaIdsWithEntitySelect($event);
        $this->findMediaIdsWithEntityMultiSelect($event);
    }

    private function findMediaIds(UnusedMediaSearchEvent $event): void
    {
        /** @var list<array{id: string, name: string, entity_name: string}> $customMediaFields */
        $customMediaFields = $this->connection->fetchAllAssociative(
            <<<SQL
            SELECT f.id, f.name, fsr.entity_name
            FROM custom_field f
            INNER JOIN custom_field_set fs ON (f.set_id = fs.id)
            INNER JOIN custom_field_set_relation fsr ON (fs.id = fsr.set_id)
            WHERE JSON_UNQUOTE(JSON_EXTRACT(f.config, '$.customFieldType')) = 'media'
            SQL
        );

        $fieldsPerEntity = $this->groupFieldsPerEntity($customMediaFields);

        $statements = [];
        foreach ($fieldsPerEntity as $entity => $fields) {
            $table = $this->getTableName((string) $entity);

            foreach ($fields as $field) {
                $statements[] = "SELECT JSON_UNQUOTE(JSON_EXTRACT({$table}.custom_fields, '$.{$field}')) as media_id FROM {$table} WHERE JSON_UNQUOTE(JSON_EXTRACT({$table}.custom_fields, '$.{$field}')) IN (?)";
            }
        }

        if (\count($statements) === 0) {
            return;
        }

        foreach ($statements as $statement) {
            $usedMediaIds = $this->connection->fetchFirstColumn(
                $statement,
                [$event->getUnusedIds()],
                [ArrayParameterType::STRING]
            );

            $event->markAsUsed($usedMediaIds);
        }
    }

    /**
     * @return list<array{id: string, name: string, entity_name: string}>
     */
    private function findCustomFieldsWithEntitySelect(string $componentType): array
    {
        /** @var list<array{id: string, name: string, entity_name: string}> $results */
        $results = $this->connection->fetchAllAssociative(
            <<<SQL
            SELECT f.id, f.name, fsr.entity_name
            FROM custom_field f
            INNER JOIN custom_field_set fs ON (f.set_id = fs.id)
            INNER JOIN custom_field_set_relation fsr ON (fs.id = fsr.set_id)
            WHERE f.type = 'select' AND JSON_UNQUOTE(JSON_EXTRACT(f.config, '$.entity')) = 'media' AND JSON_UNQUOTE(JSON_EXTRACT(f.config, '$.componentName')) = '{$componentType}'
            SQL
        );

        return $results;
    }

    private function findMediaIdsWithEntitySelect(UnusedMediaSearchEvent $event): void
    {
        $fieldsPerEntity = $this->groupFieldsPerEntity(
            $this->findCustomFieldsWithEntitySelect('sw-entity-single-select')
        );

        $statements = [];
        foreach ($fieldsPerEntity as $entity => $fields) {
            $table = $this->getTableName((string) $entity);

            foreach ($fields as $field) {
                $statements[] = "SELECT JSON_UNQUOTE(JSON_EXTRACT({$table}.custom_fields, '$.{$field}')) as media_id FROM {$table} WHERE JSON_UNQUOTE(JSON_EXTRACT({$table}.custom_fields, '$.{$field}')) IN (?)";
            }
        }

        if (\count($statements) === 0) {
            return;
        }

        foreach ($statements as $statement) {
            $usedMediaIds = $this->connection->fetchFirstColumn(
                $statement,
                [$event->getUnusedIds()],
                [ArrayParameterType::STRING]
            );

            $event->markAsUsed($usedMediaIds);
        }
    }

    private function findMediaIdsWithEntityMultiSelect(UnusedMediaSearchEvent $event): void
    {
        $fieldsPerEntity = $this->groupFieldsPerEntity(
            $this->findCustomFieldsWithEntitySelect('sw-entity-multi-id-select')
        );

        $statements = [];
        foreach ($fieldsPerEntity as $entity => $fields) {
            $table = $this->getTableName((string) $entity);

            foreach ($fields as $field) {
                $statements[] = <<<SQL
                SELECT JSON_EXTRACT(custom_fields, "$.{$field}") as mediaIds FROM {$table}
                WHERE JSON_OVERLAPS(
                    JSON_EXTRACT(custom_fields, "$.{$field}"),
                    JSON_ARRAY(?)
                );
                SQL;
            }
        }

        if (\count($statements) === 0) {
            return;
        }

        foreach ($statements as $statement) {
            $usedMediaIds = $this->connection->fetchFirstColumn(
                $statement,
                [$event->getUnusedIds()],
                [ArrayParameterType::STRING]
            );

            $event->markAsUsed(
                array_merge(
                    ...array_map(fn (string $ids) => json_decode($ids, true, \JSON_THROW_ON_ERROR), $usedMediaIds)
                )
            );
        }
    }

    private function getTableName(string $entity): string
    {
        $definition = $this->definitionRegistry->getByEntityName($entity);

        $customFields = $definition->getField('customFields');

        $table = $definition->getEntityName();

        if ($customFields instanceof TranslatedField) {
            $table = $definition->getTranslationDefinition()?->getEntityName() ?? $table;
        }

        return $table;
    }

    /**
     * @param list<array{id: string, name: string, entity_name: string}> $customMediaFields
     *
     * @return array<string, array<string>>
     */
    private function groupFieldsPerEntity(array $customMediaFields): array
    {
        $fieldsPerEntity = [];
        foreach ($customMediaFields as $field) {
            if (!isset($fieldsPerEntity[$field['entity_name']])) {
                $fieldsPerEntity[$field['entity_name']] = [];
            }
            $fieldsPerEntity[$field['entity_name']][] = $field['name'];
        }

        return $fieldsPerEntity;
    }
}
