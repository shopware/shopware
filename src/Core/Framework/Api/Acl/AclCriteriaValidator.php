<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AclCriteriaValidator
{
    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    public function __construct(DefinitionInstanceRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @throws AccessDeniedHttpException
     * @throws AssociationNotFoundException
     */
    public function validate(string $entity, Criteria $criteria, Context $context): void
    {
        $privilege = $entity . ':' . AclRoleDefinition::PRIVILEGE_READ;

        if (!$context->isAllowed($privilege)) {
            throw new MissingPrivilegeException($privilege);
        }

        $definition = $this->registry->getByEntityName($entity);

        foreach ($criteria->getAssociations() as $field => $nested) {
            $association = $definition->getField($field);

            if (!$association || !$association instanceof AssociationField) {
                throw new AssociationNotFoundException($field);
            }

            $reference = $association->getReferenceDefinition()->getEntityName();
            if ($association instanceof ManyToManyAssociationField) {
                $reference = $association->getToManyReferenceDefinition()->getEntityName();
            }

            $this->validate($reference, $nested, $context);
        }

        foreach ($criteria->getAllFields() as $accessor) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

            foreach ($fields as $field) {
                if (!$field instanceof AssociationField) {
                    continue;
                }

                $reference = $field->getReferenceDefinition()->getEntityName();
                if ($field instanceof ManyToManyAssociationField) {
                    $reference = $field->getToManyReferenceDefinition()->getEntityName();
                }

                $privilege = $reference . ':' . AclRoleDefinition::PRIVILEGE_READ;

                if (!$context->isAllowed($privilege)) {
                    throw new MissingPrivilegeException($privilege);
                }
            }
        }
    }
}
