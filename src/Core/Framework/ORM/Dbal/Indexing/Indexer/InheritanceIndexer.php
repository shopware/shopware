<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Dbal\EntityDefinitionQueryHelper;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\AssociationInterface;
use Shopware\Framework\ORM\Field\Field;
use Shopware\Framework\ORM\Field\ManyToManyAssociationField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\Inherited;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Framework\Struct\Uuid;

class InheritanceIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        /** @var WrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $nested->getDefinition();

            if ($definition::isInheritanceAware()) {
                $this->update($definition, $nested->getIds(), $nested->getContext());
            }
        }
    }

    private function update(string $definition, array $ids, ApplicationContext $context): void
    {
        /** @var string|EntityDefinition $definition */
        $inherited = $definition::getFields()->filter(function (Field $field) {
            return $field->is(Inherited::class) && $field instanceof AssociationInterface;
        });

        $associations = $inherited->filter(function (Field $field) {
            return $field instanceof OneToManyAssociationField || $field instanceof ManyToManyAssociationField || $field instanceof TranslationsAssociationField;
        });

        if ($associations->count() > 0) {
            $this->updateToManyAssociations($definition, $ids, $associations, $context);
        }

        $associations = $inherited->filter(function (Field $field) {
            return $field instanceof ManyToOneAssociationField;
        });

        if ($associations->count() > 0) {
            $this->updateToOneAssociations($definition, $ids, $associations, $context);
        }
    }

    private function updateToManyAssociations(
        string $definition,
        array $ids,
        FieldCollection $associations,
        ApplicationContext $context
    ): void {
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());
        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /* @var string|EntityDefinition $definition */
        foreach ($associations as $association) {
            if ($association instanceof ManyToManyAssociationField) {
                /** @var ManyToManyAssociationField $association */
                $reference = $association->getMappingDefinition();
            } else {
                /** @var OneToManyAssociationField $association */
                $reference = $association->getReferenceClass();
            }

            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition::getEntityName()),
                '#entity#' => $definition::getEntityName(),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
                '#reference#' => EntityDefinitionQueryHelper::escape($reference::getEntityName()),
            ];

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# SET #property# = IFNULL(
                        (
                            SELECT #reference#.#entity#_id
                            FROM   #reference#
                            WHERE  #reference#.#entity#_id         = #root#.id
                            AND    #reference#.#entity#_version_id = #root#.version_id
                            AND    #reference#.#entity#_tenant_id  = #root#.tenant_id 
                            LIMIT 1
                        ),
                        #root#.parent_id
                     )
                     WHERE #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }

    private function updateToOneAssociations(
        string $definition,
        array $ids,
        FieldCollection $associations,
        ApplicationContext $context
    ): void {
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());
        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $bytes = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        /* @var string|EntityDefinition $definition */
        /** @var Field|ManyToOneAssociationField $association */
        foreach ($associations as $association) {
            $parameters = [
                '#root#' => EntityDefinitionQueryHelper::escape($definition::getEntityName()),
                '#field#' => EntityDefinitionQueryHelper::escape($association->getStorageName()),
                '#property#' => EntityDefinitionQueryHelper::escape($association->getPropertyName()),
            ];

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# as #root#, #root# as parent
                     SET #root#.#property# = IFNULL(#root#.#field#, parent.#field#)
                     WHERE #root#.parent_id = parent.id
                     AND #root#.parent_id IS NOT NULL
                     AND #root#.version_id = parent.version_id
                     AND #root#.tenant_id = parent.tenant_id
                     AND #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );

            $this->connection->executeUpdate(
                str_replace(
                    array_keys($parameters),
                    array_values($parameters),
                    'UPDATE #root# AS #root#
                     SET #root#.#property# = #root#.#field#
                     WHERE #root#.parent_id IS NULL
                     AND #root#.version_id = :version
                     AND #root#.tenant_id = :tenant
                     AND #root#.id IN (:ids)'
                ),
                ['version' => $versionId, 'tenant' => $tenantId, 'ids' => $bytes],
                ['version' => \PDO::PARAM_STR, 'tenant' => \PDO::PARAM_STR, 'ids' => Connection::PARAM_STR_ARRAY]
            );
        }
    }
}
