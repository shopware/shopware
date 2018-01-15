<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\Flag\Deferred;
use Shopware\Api\Entity\Write\Flag\Extension;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\Struct;

class EntityReader implements EntityReaderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    public function __construct(Connection $connection, EntitySearcherInterface $searcher)
    {
        $this->connection = $connection;
        $this->searcher = $searcher;
    }

    public function readDetail(string $definition, array $ids, TranslationContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = $definition::getDetailCollectionClass();

        $structClass = $definition::getDetailStructClass();

        return $this->read(
            $ids,
            $definition,
            $context,
            new $structClass(),
            new $collectionClass(),
            $definition::getFields()->getDetailProperties()
        );
    }

    public function readBasic(string $definition, array $ids, TranslationContext $context): EntityCollection
    {
        /** @var EntityDefinition $definition */
        $collectionClass = $definition::getBasicCollectionClass();

        $structClass = $definition::getBasicStructClass();

        return $this->read(
            $ids,
            $definition,
            $context,
            new $structClass(),
            new $collectionClass(),
            $definition::getFields()->getBasicProperties()
        );
    }

    private function read(array $ids, string $definition, TranslationContext $context, Entity $entity, EntityCollection $collection, FieldCollection $fields): EntityCollection
    {
        if (empty($ids)) {
            return $collection;
        }

        /** @var EntityDefinition $definition */
        $rows = $this->fetch($ids, $definition, $context, $fields);
        foreach ($rows as $row) {
            $collection->add(
                EntityHydrator::hydrate(clone $entity, $definition, $row, $definition::getEntityName())
            );
        }

        /** @var EntityDefinition $reference */
        $associations = $fields->filterInstance(ManyToOneAssociationField::class);
        /** @var ManyToOneAssociationField[] $associations */
        foreach ($associations as $association) {
            $this->loadManyToOne($definition, $association, $context, $collection);
        }

        /** @var OneToManyAssociationField[] $associations */
        $associations = $fields->filterInstance(OneToManyAssociationField::class);
        foreach ($associations as $association) {
            $this->loadOneToMany($ids, $association, $context, $collection);
        }

        /** @var ManyToManyAssociationField[] $associations */
        $associations = $fields->filterInstance(ManyToManyAssociationField::class);
        foreach ($associations as $association) {
            $this->loadManyToMany($association, $context, $collection);
        }

        $collection->sortByIdArray($ids);

        return $collection;
    }

    private function joinBasic(
        string $definition,
        TranslationContext $context,
        string $root,
        QueryBuilder $query,
        FieldCollection $fields
    ): void {
        /** @var EntityDefinition $definition */
        $fields = $fields->filter(function (Field $field) {
            return !$field->is(Deferred::class);
        });

        foreach ($fields as $field) {
            //translated fields are handled after loop all together
            if ($field instanceof TranslatedField) {
                continue;
            }

            //self references can not be resolved, otherwise we get an endless loop
            if ($field instanceof AssociationInterface && $field->getReferenceClass() === $definition) {
                continue;
            }

            //many to one associations can be directly fetched in same query
            if ($field instanceof ManyToOneAssociationField) {
                /** @var EntityDefinition $reference */
                $reference = $field->getReferenceClass();

                $basics = $reference::getFields()->getBasicProperties();

                if ($this->requiresToManyAssociation($basics)) {
                    continue;
                }

                EntityDefinitionResolver::joinManyToOne($root, $field, $query);

                $alias = $root . '.' . $field->getPropertyName();
                $this->joinBasic($field->getReferenceClass(), $context, $alias, $query, $basics);

                continue;
            }

            //add sub select for many to many field
            if ($field instanceof ManyToManyAssociationField) {
                $this->addManyToManySelect($root, $field, $query);
                continue;
            }

            //other associations like OneToManyAssociationField fetched lazy by additional query
            if ($field instanceof AssociationInterface) {
                continue;
            }

            //all other StorageAware fields are stored inside the main entity
            if ($field instanceof StorageAware) {
                /* @var Field $field */
                $query->addSelect(
                    EntityDefinitionResolver::escape($root) . '.' . EntityDefinitionResolver::escape(
                        $field->getStorageName()
                    )
                    . ' as ' .
                    EntityDefinitionResolver::escape($root . '.' . $field->getPropertyName())
                );
                continue;
            }
        }

        $translatedFields = $fields->filterInstance(TranslatedField::class);
        if ($translatedFields->count() <= 0) {
            return;
        }

        EntityDefinitionResolver::addTranslationSelect($root, $definition, $query, $context, $translatedFields);
    }

    /**
     * @param array                   $ids
     * @param string|EntityDefinition $definition
     * @param TranslationContext      $context
     * @param FieldCollection         $fields
     *
     * @return array
     */
    private function fetch(array $ids, string $definition, TranslationContext $context, FieldCollection $fields): array
    {
        $table = $definition::getEntityName();

        $query = new QueryBuilder($this->connection);
        $query->from(EntityDefinitionResolver::escape($table), EntityDefinitionResolver::escape($table));

        $this->joinBasic($definition, $context, $table, $query, $fields);

        $query->andWhere(EntityDefinitionResolver::escape($table) . '.`id` IN (:ids)');
        $query->setParameter('ids', array_values(EntityDefinitionResolver::uuidStringsToBytes($ids)), Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll();
    }

    /**
     * @param string|EntityDefinition   $definition
     * @param ManyToOneAssociationField $association
     * @param TranslationContext        $context
     * @param EntityCollection          $collection
     */
    private function loadManyToOne(string $definition, ManyToOneAssociationField $association, TranslationContext $context, EntityCollection $collection)
    {
        $reference = $association->getReferenceClass();

        $fields = $reference::getFields()->getBasicProperties();
        if (!$this->requiresToManyAssociation($fields)) {
            return;
        }

        $field = $definition::getFields()->getByStorageName($association->getStorageName());

        $ids = $collection->map(function (Entity $entity) use ($field) {
            return $entity->get($field->getPropertyName());
        });

        $data = $this->readBasic($association->getReferenceClass(), $ids, $context);

        /** @var Entity $struct */
        foreach ($collection as $struct) {
            $id = $struct->get($field->getPropertyName());

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $data->get($id));
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $data->get($id),
            ]);
        }
    }

    private function loadOneToMany(array $ids, OneToManyAssociationField $association, TranslationContext $context, EntityCollection $collection): void
    {
        $reference = $association->getReferenceClass();

        $field = $reference::getFields()->getByStorageName($association->getReferenceField());

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery($reference::getEntityName() . '.' . $field->getPropertyName(), $ids));

        $associationIds = $this->searcher->search($reference, $criteria, $context);

        $data = $this->readBasic($reference, $associationIds->getIds(), $context);

        /** @var Struct|Entity $struct */
        foreach ($collection as $struct) {
            //filter by property allows to avoid building the getter function name
            $structData = $data->filterByProperty($field->getPropertyName(), $struct->getId());

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function loadManyToMany(ManyToManyAssociationField $association, TranslationContext $context, EntityCollection $collection): void
    {
        $idProperty = $association->getStructIdMappingProperty();

        //collect all ids of many to many association which already stored inside the struct instances
        $ids = $this->collectManyToManyIds($collection, $idProperty);

        $data = $this->readBasic($association->getReferenceDefinition(), $ids, $context);

        foreach ($collection as $struct) {
            //use assign function to avoid setter name building
            $structData = $data->getList($struct->get($idProperty));

            if ($association->is(Extension::class)) {
                $struct->addExtension($association->getPropertyName(), $structData);
                continue;
            }

            $struct->assign([
                $association->getPropertyName() => $structData,
            ]);
        }
    }

    private function addManyToManySelect(string $root, ManyToManyAssociationField $field, QueryBuilder $query): void
    {
        /** @var EntityDefinition $mapping */
        $mapping = $field->getMappingDefinition();

        $query->addSelect(
            str_replace(
                [
                    '#alias#',
                    '#mapping_reference_column#',
                    '#mapping_table#',
                    '#mapping_local_column#',
                    '#root#',
                    '#source_column#',
                    '#property#',
                ],
                [
                    EntityDefinitionResolver::escape($root . '.' . $field->getPropertyName() . '.mapping'),
                    EntityDefinitionResolver::escape($field->getMappingReferenceColumn()),
                    EntityDefinitionResolver::escape($mapping::getEntityName()),
                    EntityDefinitionResolver::escape($field->getMappingLocalColumn()),
                    EntityDefinitionResolver::escape($root),
                    EntityDefinitionResolver::escape('id'),
                    EntityDefinitionResolver::escape($root . '.' . $field->getPropertyName()),
                ],
                '(SELECT GROUP_CONCAT(#alias#.#mapping_reference_column# SEPARATOR \'|||\')
                  FROM #mapping_table# #alias#
                  WHERE #alias#.#mapping_local_column# = #root#.#source_column#) as #property#'
            )
        );
    }

    private function collectManyToManyIds(EntityCollection $collection, string $property): array
    {
        $ids = [];
        foreach ($collection as $struct) {
            $tmp = $struct->get($property);
            foreach ($tmp as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param $fields
     *
     * @return mixed
     */
    private function requiresToManyAssociation(FieldCollection $fields)
    {
        foreach ($fields as $field) {
            if (!$field instanceof AssociationInterface) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
                return true;
            }

            /** @var ManyToOneAssociationField $field */
            $reference = $field->getReferenceClass();

            if ($this->requiresToManyAssociation($reference::getFields()->getBasicProperties())) {
                return true;
            }
        }

        return false;
    }
}
