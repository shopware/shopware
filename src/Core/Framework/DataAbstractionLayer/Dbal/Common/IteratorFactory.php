<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @final
 */
#[Package('framework')]
class IteratorFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry
    ) {
    }

    /**
     * @param array{offset: int|string|null}|null $lastId
     */
    public function createIterator(string|EntityDefinition $definition, ?array $lastId = null, int $limit = 50, ?string $versionId = null): IterableQuery
    {
        if (\is_string($definition)) {
            $definition = $this->registry->getByEntityName($definition);
        }

        $entity = $definition->getEntityName();

        $escaped = EntityDefinitionQueryHelper::escape($entity);
        $index = $this->getIterationIndex($definition);
        if ($index === null) {
            $query = new QueryBuilder($this->connection);
            $query->from($escaped);
        } else {
            $query = new PrimaryKeyScanQueryBuilder($this->connection, $entity, $index);
            $query->orderBy($escaped . '.id');
        }
        $query->setMaxResults($limit);

        if ($definition->isVersionAware() && $versionId !== null) {
            $versionColumn = 'version_id';

            if ($definition instanceof EntityTranslationDefinition) {
                $parent = $definition->getParentDefinition()->getClass();
                $referenceVersion = $definition->getPrimaryKeys()->filter(
                    static fn (Field $field) => $field instanceof ReferenceVersionField && $field->getVersionReferenceClass() === $parent
                )->first();

                if (!$referenceVersion instanceof ReferenceVersionField) {
                    throw DataAbstractionLayerException::versionFieldNotFound($definition->getEntityName());
                }

                $versionColumn = $referenceVersion->getStorageName();
            }

            $query->andWhere($escaped . '.' . EntityDefinitionQueryHelper::escape($versionColumn) . ' = :versionId');
            $query->setParameter('versionId', Uuid::fromHexToBytes($versionId));
        }

        if ($definition->hasAutoIncrement()) {
            $query->select($escaped . '.auto_increment', 'LOWER(HEX(' . $escaped . '.id)) as id');
            $query->andWhere($escaped . '.auto_increment > :lastId');
            $query->addOrderBy($escaped . '.auto_increment');
            $query->setParameter('lastId', 0);

            if ($lastId !== null) {
                $query->setParameter('lastId', $lastId['offset']);
            }

            return new LastIdQuery($query);
        }

        $primaryKeys = $definition->getPrimaryKeys();
        $primaryKey = $primaryKeys->first();
        $offset = $lastId['offset'] ?? null;
        // Jobs queued before UUID pagination must retain their numeric row offsets.
        if ($primaryKeys->count() === 1 && $primaryKey instanceof IdField && $primaryKey->getStorageName() === 'id'
            && !\is_int($offset) && (Feature::isActive('v6.8.0.0') || \is_string($offset))) {
            $query->select($escaped . '.id', 'LOWER(HEX(' . $escaped . '.id)) as id');
            $query->orderBy($escaped . '.id');
            $predicate = $escaped . '.id > :lastId';
            if ($offset === null) {
                return new LastIdQuery($query, $predicate);
            }

            $query->andWhere($predicate);
            $query->setParameter('lastId', Uuid::fromHexToBytes($offset), ParameterType::BINARY);

            return new LastIdQuery($query);
        }

        $id = $escaped . '.id';

        if ($definition instanceof EntityTranslationDefinition) {
            $translationField = $definition->getParentDefinition()->getTranslationField();
            if ($translationField === null) {
                throw DataAbstractionLayerException::missingParentForeignKey($definition->getParentDefinition()->getEntityName());
            }

            $id = $escaped . '.' . EntityDefinitionQueryHelper::escape($translationField->getReferenceField());
            $query->distinct();
            $query->orderBy($id);
        }

        $query->select($id, 'LOWER(HEX(' . $id . '))');
        $query->setFirstResult(0);
        if ($lastId !== null) {
            $query->setFirstResult((int) $lastId['offset']);
        }

        return new OffsetQuery($query);
    }

    private function getIterationIndex(EntityDefinition $definition): ?string
    {
        $field = $definition->getField('id');
        $index = $field?->getFlag(PrimaryKey::class)?->iterationIndex;
        if ($index === null || !$field instanceof IdField || $field->getStorageName() !== 'id'
            || $definition->getPrimaryKeys()->count() !== 1 || $definition->hasAutoIncrement()
            || $definition->isVersionAware() || $definition instanceof EntityTranslationDefinition) {
            return null;
        }

        return $index;
    }
}
