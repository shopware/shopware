<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineQueryBuilder;
use Doctrine\DBAL\Result;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;

/**
 * @internal
 */
#[Package('data-services')]
class DispatchEntitiesQueryBuilder
{
    public const PUID_FIELD_NAME = 'puid';

    private readonly QueryBuilder $queryBuilder;

    public function __construct(Connection $connection)
    {
        $this->queryBuilder = new QueryBuilder($connection);
    }

    public function getQueryBuilder(): DoctrineQueryBuilder
    {
        return $this->queryBuilder;
    }

    public function forEntity(string $entityName): self
    {
        $this->queryBuilder->setTitle("UsageData EntitySync - dispatch entities for '$entityName'");
        $this->queryBuilder->from(EntityDefinitionQueryHelper::escape($entityName));

        return $this;
    }

    public function withFields(FieldCollection $fields): self
    {
        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            $this->queryBuilder->addSelect(EntityDefinitionQueryHelper::escape($field->getStorageName()));
        }

        return $this;
    }

    public function withPersonalUniqueIdentifier(): self
    {
        $concatenatedFields = array_map(
            static fn (string $field) => sprintf('LOWER(%s)', EntityDefinitionQueryHelper::escape($field)),
            [
                EntityDefinitionService::PUID_FIELDS['firstName'],
                EntityDefinitionService::PUID_FIELDS['lastName'],
                EntityDefinitionService::PUID_FIELDS['email'],
            ]
        );

        $this->queryBuilder->addSelect(sprintf(
            'SHA2(CONCAT(%s), 512) AS %s',
            implode(', ', $concatenatedFields),
            EntityDefinitionQueryHelper::escape(self::PUID_FIELD_NAME)
        ));

        return $this;
    }

    /**
     * @param array<int, array<string, string>> $primaryKeys
     */
    public function withPrimaryKeys(array $primaryKeys): self
    {
        $primaryKeyConditions = null;

        $pkCount = 0;
        foreach ($primaryKeys as $primaryKey) {
            $combinedKeyCondition = null;

            foreach ($primaryKey as $column => $id) {
                ++$pkCount;
                $condition = sprintf('%s = :pk_%s', EntityDefinitionQueryHelper::escape($column), (string) $pkCount);
                $this->queryBuilder->setParameter(sprintf('pk_%s', (string) $pkCount), Uuid::fromHexToBytes($id));

                $combinedKeyCondition = $combinedKeyCondition === null
                    ? CompositeExpression::and($condition)
                    : $combinedKeyCondition->with($condition);
            }

            if ($combinedKeyCondition) {
                $primaryKeyConditions = $primaryKeyConditions === null
                    ? CompositeExpression::or($combinedKeyCondition)
                    : $primaryKeyConditions->with($combinedKeyCondition);
            }
        }

        if ($primaryKeyConditions !== null) {
            $this->queryBuilder->andWhere($primaryKeyConditions);
        }

        return $this;
    }

    public function checkLiveVersion(EntityDefinition $definition): self
    {
        $hasVersionFields = false;

        foreach ($definition->getFields() as $field) {
            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                $hasVersionFields = true;
                $this->queryBuilder->andWhere(
                    sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape($field->getStorageName())),
                );
            }
        }

        if ($hasVersionFields) {
            $this->queryBuilder->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));
        }

        return $this;
    }

    public function withLastApprovalDateConstraint(DispatchEntityMessage $message, \DateTimeInterface $lastApprovalDate): self
    {
        $escapedUpdatedAtColumnName = EntityDefinitionQueryHelper::escape('updated_at');

        if ($message->operation === Operation::CREATE) {
            $this->queryBuilder->andWhere(
                CompositeExpression::or(
                    $this->queryBuilder->expr()->isNull($escapedUpdatedAtColumnName),
                    $this->queryBuilder->expr()->lte($escapedUpdatedAtColumnName, ':lastApprovalDate'),
                )
            );

            $this->queryBuilder->setParameter('lastApprovalDate', $lastApprovalDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        }

        if ($message->operation === Operation::UPDATE) {
            $this->queryBuilder->andWhere(
                $this->queryBuilder->expr()->lte($escapedUpdatedAtColumnName, ':lastApprovalDate')
            );

            $this->queryBuilder->setParameter('lastApprovalDate', $lastApprovalDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        }

        return $this;
    }

    public function execute(): Result
    {
        return $this->queryBuilder->executeQuery();
    }
}
