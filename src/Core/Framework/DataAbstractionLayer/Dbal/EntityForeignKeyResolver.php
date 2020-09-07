<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageDefinition;

/**
 * Determines all associated data for a definition.
 * Used to determines which associated will be deleted to or which associated data would restrict a delete operation.
 */
class EntityForeignKeyResolver
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(Connection $connection, EntityDefinitionQueryHelper $queryHelper)
    {
        $this->connection = $connection;
        $this->queryHelper = $queryHelper;
    }

    /**
     * Returns a list of all entities and their primary keys which will restrict the delete in the mysql server
     * Example:
     *  [
     *      "order_customer" => array:2 [
     *          "cace68bdbca140b6ac43a083fb19f82b",
     *          "50330f5531ed485fbd72ba016b20ea2a",
     *      ]
     *      "order_address" => array:4 [
     *          "29d6334b01e64be28c89a5f1757fd661",
     *          "484ef1124595434fa9b14d6d2cc1e9f8",
     *          "601133b1173f4ca3aeda5ef64ad38355",
     *          "9fd6c61cf9844a8984a45f4e5b55a59c",
     *      ]
     *  ]
     *
     * @throws \RuntimeException
     */
    public function getAffectedDeleteRestrictions(EntityDefinition $definition, array $ids, Context $context): array
    {
        return $this->fetch($definition, $ids, RestrictDelete::class, $context);
    }

    /**
     * Returns a list of all entities and their primary keys which will be deleted by the mysql server
     * Example:
     *  [
     *      "order_customer" => array:2 [
     *          "cace68bdbca140b6ac43a083fb19f82b",
     *          "50330f5531ed485fbd72ba016b20ea2a",
     *      ]
     *      "order_address" => array:4 [
     *          "29d6334b01e64be28c89a5f1757fd661",
     *          "484ef1124595434fa9b14d6d2cc1e9f8",
     *          "601133b1173f4ca3aeda5ef64ad38355",
     *          "9fd6c61cf9844a8984a45f4e5b55a59c",
     *      ]
     *  ]
     *
     * @throws \RuntimeException
     */
    public function getAffectedDeletes(EntityDefinition $definition, array $ids, Context $context): array
    {
        return $this->fetch($definition, $ids, CascadeDelete::class, $context);
    }

    /**
     * Returns an associated nested array which contains all affected set null on delete entities.
     * Example:
     *   [
     *       'product.manufacturer_id' => [
     *           '1ffd7ea958c643558256927aae8efb07'
     *           '1ffd7ea958c643558256927aae8efb07'
     *       ]
     *   ]
     *
     * @throws \RuntimeException
     */
    public function getAffectedSetNulls(EntityDefinition $definition, array $ids, Context $context): array
    {
        return $this->fetch($definition, $ids, SetNullOnDelete::class, $context);
    }

    /**
     * @throws InvalidUuidException
     */
    private function fetch(EntityDefinition $definition, array $ids, string $class, Context $context): array
    {
        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return [];
        }

        if (!$definition->getFields()->has('id')) {
            return [];
        }

        //prevent foreign key check for language definition, otherwise all ids of language translations has to be checked
        if ($definition->getClass() === LanguageDefinition::class) {
            return [];
        }

        $cascades = $definition->getFields()->filter(static function (Field $field) use ($class): bool {
            return $field->is($class);
        });

        if ($cascades->count() === 0) {
            return [];
        }

        $result = [];
        foreach ($cascades as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            $affected = $this->fetchAssociation($ids, $definition, $association, $class, $context);

            $result = array_merge($result, $affected);
        }

        return $result;
    }

    private function fetchAssociation(array $ids, EntityDefinition $root, AssociationField $association, string $class, Context $context): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = new QueryBuilder($this->connection);
        $query->from(
            EntityDefinitionQueryHelper::escape($root->getEntityName()),
            EntityDefinitionQueryHelper::escape($root->getEntityName())
        );

        $this->queryHelper->resolveField($association, $root, $root->getEntityName(), $query, $context);

        $alias = $root->getEntityName() . '.' . $association->getPropertyName();

        if ($association instanceof ManyToManyAssociationField) {
            $alias .= '.mapping';
        }

        $primaryKeys = $association->getReferenceDefinition()->getPrimaryKeys()->filter(function (Field $field) {
            if ($field instanceof ReferenceVersionField || $field instanceof VersionField) {
                return null;
            }

            return $field;
        });

        foreach ($primaryKeys as $field) {
            $vars = [
                '#root#' => EntityDefinitionQueryHelper::escape($alias),
                '#field#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
                '#property#' => $field->getPropertyName(),
            ];

            $template = '#root#.#field# as #property#';
            if ($field instanceof IdField || $field instanceof FkField) {
                $template = 'LOWER(HEX(#root#.#field#)) as #property#';
            }

            $accessor = str_replace(array_keys($vars), array_values($vars), $template);
            $query->addSelect($accessor);

            $accessor = str_replace(array_keys($vars), array_values($vars), '#root#.#field#');
            $query->andWhere($accessor . ' IS NOT NULL');
        }

        if ($root->isVersionAware()) {
            $query->andWhere(EntityDefinitionQueryHelper::escape($root->getEntityName()) . '.`version_id` = :version');
            $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        }

        $this->queryHelper->addIdCondition(new Criteria($ids), $root, $query);

        $affected = $query->execute()->fetchAll();

        if (empty($affected)) {
            return [];
        }

        // create flat list for single primary key entities
        if ($primaryKeys->count() === 1) {
            $property = $primaryKeys->first()->getPropertyName();
            $affected = array_column($affected, $property);
        }

        // prevent circular reference for many to many
        if ($association instanceof ManyToManyAssociationField) {
            return [$association->getReferenceDefinition()->getEntityName() => $affected];
        }

        if ($class === SetNullOnDelete::class) {
            // add entity prefix for the current association
            $formatted = [$association->getReferenceDefinition()->getEntityName() . '.' . $association->getReferenceField() => $affected];
        } else {
            // add entity prefix for the current association
            $formatted = [$association->getReferenceDefinition()->getEntityName() => $affected];
        }

        // call recursion for nested cascades
        $nested = $this->fetch($association->getReferenceDefinition(), $affected, $class, $context);

        return array_merge($formatted, $nested);
    }
}
