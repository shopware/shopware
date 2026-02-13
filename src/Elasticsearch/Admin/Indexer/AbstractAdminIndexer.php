<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use OpenSearchDSL\Search;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @template IDStructure of string|array<string, string> = string
 */
#[Package('inventory')]
abstract class AbstractAdminIndexer
{
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

    public function globalCriteria(string $term, Search $criteria): Search
    {
        return $criteria;
    }

    /**
     * @return array<string>
     */
    public function getSupportedSearchFields(): array
    {
        $supportedFields = [];

        $mapping = $this->mapping([])['properties'] ?? [];

        if ($mapping === []) {
            return [];
        }

        foreach ($mapping as $field => $type) {
            if (\array_key_exists('properties', $type) && !empty($type['properties'])) {
                foreach (array_keys($type['properties']) as $property) {
                    if (!\is_string($property) || $property === '_count') {
                        continue;
                    }

                    // adding original translated field, property in this case is the language id
                    if (Uuid::isValid($property)) {
                        $supportedFields[] = $field;

                        break;
                    }

                    $supportedFields[] = $field . '.' . $property;
                }

                continue;
            }

            $supportedFields[] = $field;
        }

        foreach ($supportedFields as $field) {
            $supportedFields[] = $this->getEntity() . '.' . $field;
        }

        return $supportedFields;
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
}
