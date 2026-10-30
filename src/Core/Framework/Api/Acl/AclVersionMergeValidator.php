<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * Checks the privileges needed to merge a version.
 *
 * The update privilege of the merged entity covers its own changes, its translations and the changes of its child entities.
 * Changes of other entities need their own privileges.
 *
 * @internal
 */
#[Package('fundamentals@framework')]
class AclVersionMergeValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $definitionRegistry)
    {
    }

    /**
     * @return list<string>
     */
    public function getMissingPrivileges(string $versionId, EntityDefinition $definition, Context $context): array
    {
        $missing = [];

        $entityPrivilege = $this->getMissingPrivilege($context, $definition, AclRoleDefinition::PRIVILEGE_UPDATE);
        if ($entityPrivilege !== null) {
            $missing[$entityPrivilege] = $entityPrivilege;
        }

        if ($versionId === Defaults::LIVE_VERSION || !$definition->isVersionAware()) {
            return array_values($missing);
        }

        // collect which entities the version changed and how
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('commit.versionId', $versionId));
        $criteria->addAggregation(new TermsAggregation('entities', 'entityName', null, null, new TermsAggregation('actions', 'action')));

        $entities = $this->definitionRegistry
            ->getRepository(VersionCommitDataDefinition::ENTITY_NAME)
            ->aggregate($criteria, $context)
            ->get('entities');
        \assert($entities instanceof TermsResult);

        foreach ($entities->getBuckets() as $entityBucket) {
            $actions = $entityBucket->getResult();
            \assert($actions instanceof TermsResult);

            foreach ($actions->getKeys() as $action) {
                $privilege = $this->getMissingChangePrivilege($context, $definition, (string) $entityBucket->getKey(), $action);
                if ($privilege !== null) {
                    $missing[$privilege] = $privilege;
                }
            }
        }

        return array_values($missing);
    }

    private function getMissingChangePrivilege(Context $context, EntityDefinition $mergedDefinition, string $entityName, string $action): ?string
    {
        $privilege = match ($action) {
            'insert' => AclRoleDefinition::PRIVILEGE_CREATE,
            'update' => AclRoleDefinition::PRIVILEGE_UPDATE,
            'delete' => AclRoleDefinition::PRIVILEGE_DELETE,
            default => null,
        };

        if ($privilege === null || !$this->definitionRegistry->has($entityName)) {
            return null;
        }

        $definition = $this->definitionRegistry->getByEntityName($entityName);

        // the merged entity, its translations and its child entities, e.g. order_delivery_position of order
        for ($parent = $definition; $parent !== null; $parent = $parent->getParentDefinition()) {
            if ($parent->getEntityName() === $mergedDefinition->getEntityName()) {
                return null;
            }
        }

        return $this->getMissingPrivilege($context, $definition, $privilege);
    }

    private function getMissingPrivilege(Context $context, EntityDefinition $definition, string $privilege): ?string
    {
        $resource = $definition->getEntityName();

        if ($definition instanceof EntityTranslationDefinition) {
            $resource = $definition->getParentDefinition()->getEntityName();
        }

        if (!$context->isAllowed($resource . ':' . $privilege)) {
            return $resource . ':' . $privilege;
        }

        return null;
    }
}
