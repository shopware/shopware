<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class InheritanceUpdater
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
     * @param array<string> $ids
     */
    public function update(string $entity, array $ids, Context $context): void
    {
        $ids = array_unique(array_filter($ids));
        if ($ids === []) {
            return;
        }

        $definition = $this->registry->getByEntityName($entity);

        $inherited = $definition->getFields()->filter(static fn (Field $field) => $field->is(Inherited::class) && $field instanceof AssociationField);

        $associations = $inherited->filter(static fn (Field $field) => $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField || $field instanceof OneToOneAssociationField);

        if ($associations->count() > 0) {
            $this->updateToManyAssociations($definition, $ids, $associations, $context);
        }

        $associations = $inherited->filter(static fn (Field $field) => $field instanceof ManyToOneAssociationField);

        if ($associations->count() > 0) {
            $this->updateToOneAssociations($definition, $ids, $associations, $context);
        }
    }

    /**
     * @param array<string> $ids
     */
    private function updateToManyAssociations(EntityDefinition $definition, array $ids, FieldCollection $associations, Context $context): void
    {
        $bytes = array_map(static fn ($id) => Uuid::fromHexToBytes($id), $ids);

        $root = EntityDefinitionQueryHelper::escape($definition->getEntityName());

        $assignments = [];

        /** @var AssociationField $association */
        foreach ($associations as $association) {
            $reference = $association->getReferenceDefinition();

            $flag = $association->getFlag(Inherited::class);

            if (!$flag instanceof Inherited) {
                throw DataAbstractionLayerException::associationNotInherited($definition->getEntityName() . '.' . $association->getPropertyName());
            }

            $foreignKey = $flag->getForeignKey() ?: ($definition->getEntityName() . '_id');

            $versionKey = \substr($foreignKey, 0, -3) . '_version_id';

            $assignment = \sprintf(
                '#property# = IFNULL(
                        (
                            SELECT #reference#.#entity_id#
                            FROM   #reference#
                            WHERE  #reference#.#entity_id#         = #root#.id
                            %s
                            LIMIT 1
                        ),
                        IFNULL(#root#.parent_id, #root#.id)
                     )',
                $definition->isVersionAware() ? 'AND #reference#.#entity_version_id# = #root#.version_id' : ''
            );

            $parameters = [
                '#root#' => $root,
                '#entity_id#' => EntityDefinitionQueryHelper::escape($foreignKey),
                '#entity_version_id#' => EntityDefinitionQueryHelper::escape($versionKey),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
                '#reference#' => EntityDefinitionQueryHelper::escape($reference->getEntityName()),
            ];

            $assignments[] = str_replace(
                array_keys($parameters),
                $parameters,
                $assignment
            );
        }

        $sql = \sprintf(
            'UPDATE %s SET %s WHERE %s.id IN (:ids) %s',
            $root,
            implode(', ', $assignments),
            $root,
            $definition->isVersionAware() ? \sprintf('AND %s.version_id = :version', $root) : ''
        );

        $params = ['ids' => $bytes];

        if ($definition->isVersionAware()) {
            $params['version'] = Uuid::fromHexToBytes($context->getVersionId());
        }

        RetryableQuery::retryable($this->connection, function () use ($params, $sql): void {
            $this->connection->executeStatement(
                $sql,
                $params,
                ['ids' => ArrayParameterType::BINARY]
            );
        });
    }

    /**
     * @param array<string> $ids
     */
    private function updateToOneAssociations(EntityDefinition $definition, array $ids, FieldCollection $associations, Context $context): void
    {
        $bytes = array_map(static fn ($id) => Uuid::fromHexToBytes($id), $ids);

        /** @var ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            if (!$association instanceof ManyToOneAssociationField) {
                continue;
            }

            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                '#field#' => EntityDefinitionQueryHelper::escape($association->getStorageName()),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
            ];

            $sql = 'UPDATE #root# as #root#, #root# as parent
                    SET #root#.#property# = IFNULL(#root#.#field#, parent.#field#)
                    WHERE #root#.parent_id = parent.id
                    AND #root#.parent_id IS NOT NULL
                    AND #root#.id IN (:ids)';

            $params = ['ids' => $bytes];

            if ($definition->isVersionAware()) {
                $sql .= 'AND #root#.version_id = parent.version_id
                         AND #root#.version_id = :version';
                $versionId = Uuid::fromHexToBytes($context->getVersionId());
                $params['version'] = $versionId;
            }

            $sql = str_replace(
                array_keys($parameters),
                $parameters,
                $sql
            );

            RetryableQuery::retryable($this->connection, function () use ($sql, $params): void {
                $this->connection->executeStatement(
                    $sql,
                    $params,
                    ['ids' => ArrayParameterType::BINARY]
                );
            });

            $sql = 'UPDATE #root# AS #root#
                    SET #root#.#property# = #root#.#field#
                    WHERE #root#.parent_id IS NULL
                    AND #root#.id IN (:ids)';

            $params = ['ids' => $bytes];

            if ($definition->isVersionAware()) {
                $sql .= 'AND #root#.version_id = :version';
                $versionId = Uuid::fromHexToBytes($context->getVersionId());
                $params['version'] = $versionId;
            }

            $sql = str_replace(
                array_keys($parameters),
                array_values($parameters),
                $sql
            );

            RetryableQuery::retryable($this->connection, function () use ($sql, $params): void {
                $this->connection->executeStatement(
                    $sql,
                    $params,
                    ['ids' => ArrayParameterType::BINARY]
                );
            });
        }
    }
}
