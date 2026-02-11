<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin\Indexer;

use OpenSearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

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
                    if ($property === '_count') {
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
}
