<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use OpenSearchDSL\Search;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('inventory')]
abstract class AbstractAdminIndexer
{
    final public const SEARCH_FIELD = [
        'type' => 'text',
        'fields' => [
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

    abstract public function getDecorated(): self;

    abstract public function getName(): string;

    abstract public function getEntity(): string;

    /**
     * @param array{ properties?: array<string, array<mixed>> } $mapping
     *
     * @return array{ properties?: array<string, array<mixed>> }
     */
    public function mapping(array $mapping): array
    {
        return $mapping;
    }

    abstract public function getIterator(): IterableQuery;

    /**
     * @return list<string>
     */
    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        return $event->getPrimaryKeys($this->getEntity());
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array{id:string, text:string}>
     */
    abstract public function fetch(array $ids): array;

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data: EntityCollection<covariant Entity>}
     *
     * Returns EntityCollection<Entity> and their total by ids in the result parameter
     */
    abstract public function globalData(array $result, Context $context): array;

    /**
     * @description use for \Shopware\Elasticsearch\Admin\AdminSearcher::search for the global api/es-search endpoint
     */
    public function globalCriteria(string $term, Search $criteria): Search
    {
        return $criteria;
    }

    /**
     * @description use for \Shopware\Elasticsearch\Admin\AdminSearcher::searchIds for api/{entity}/search-ids endpoint
     */
    public function moduleCriteria(string $term, Search $criteria): Search
    {
        return $criteria;
    }

    /**
     * @return array<string>
     */
    public function getSupportedSearchFields(): array
    {
        $mapping = $this->mapping([
            'properties' => [
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD, // id is always supported
            ],
        ])['properties'] ?? [];

        if ($mapping === []) {
            return [];
        }

        $supportedFields = $this->collectSupportedSearchFields($mapping);

        $prefixedFields = $supportedFields;
        foreach ($supportedFields as $field) {
            $prefixedFields[] = $this->getEntity() . '.' . $field;
        }

        return $prefixedFields;
    }

    /**
     * @return array<string, string>
     */
    protected function decodeTranslatedValues(?string $encoded, string $field = 'name'): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }

        /** @var list<array<string, string|null>|null> $decoded */
        $decoded = json_decode($encoded, true, 512, \JSON_THROW_ON_ERROR);

        $translations = [];
        foreach ($decoded as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $languageId = $entry['languageId'] ?? null;
            $value = $entry[$field] ?? null;

            if (!\is_string($languageId) || $languageId === '' || !\is_string($value) || $value === '') {
                continue;
            }

            $translations[$languageId] = $value;
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{id: string, _count: int}>
     */
    protected function parseTagIds(array $row, string $key = 'tagIds'): array
    {
        if (!isset($row[$key]) || $row[$key] === '') {
            return [];
        }

        return array_map(static fn (string $tagId) => [
            'id' => $tagId,
            '_count' => 1,
        ], explode(' ', (string) $row[$key]));
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function formatDateTime(array $row, string $key): ?string
    {
        if (!isset($row[$key])) {
            return null;
        }

        return (new \DateTime((string) $row[$key]))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    /**
     * @param array<string, array<mixed>> $properties
     *
     * @return list<string>
     */
    private function collectSupportedSearchFields(array $properties, string $prefix = ''): array
    {
        $supportedFields = [];

        foreach ($properties as $field => $definition) {
            if (!\is_string($field) || $field === '_count') {
                continue;
            }

            $fieldName = $prefix === '' ? $field : $prefix . '.' . $field;

            $subProperties = $definition['properties'] ?? [];
            if (\is_array($subProperties) && $subProperties !== []) {
                if ($this->isTranslationMapping($subProperties)) {
                    $supportedFields[] = $fieldName;

                    continue;
                }

                $supportedFields = array_merge(
                    $supportedFields,
                    $this->collectSupportedSearchFields($subProperties, $fieldName)
                );

                continue;
            }

            $supportedFields[] = $fieldName;
        }

        return $supportedFields;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function isTranslationMapping(array $properties): bool
    {
        foreach ($properties as $property => $_definition) {
            if (!\is_string($property) || $property === '_count') {
                continue;
            }

            if (Uuid::isValid($property)) {
                return true;
            }
        }

        return false;
    }
}
