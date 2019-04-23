<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\Type;

use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotTypeDataResolverInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

abstract class TypeDataResolver implements SlotTypeDataResolverInterface
{
    protected function resolveEntityValue(?Entity $entity, string $path)
    {
        if ($entity === null) {
            return $entity;
        }

        $value = $entity;
        $parts = explode('.', $path);

        // if property does not exist, try to omit the first key as it may contains the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while (count($parts) > 0) {
            $part = array_shift($parts);

            if ($value === null) {
                break;
            }

            try {
                $value = $value->get($part);
            } catch (\InvalidArgumentException $ex) {
                if (!$smartDetect) {
                    throw $ex;
                }
            }

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;
        }

        return $value;
    }

    /**
     * @param string|EntityDefinition $definition
     */
    protected function resolveDefinitionField(string $definition, string $path): ?Field
    {
        $value = null;
        $parts = explode('.', $path);
        $fields = $definition::getFields();

        // if property does not exist, try to omit the first key as it may contains the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while (count($parts) > 0) {
            $part = array_shift($parts);
            $value = $fields->get($part);

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;

            if ($value instanceof AssociationField) {
                $fields = $value->getReferenceClass()::getFields();
            }
        }

        return $value;
    }

    protected function resolveCriteriaForLazyLoadedRelations(EntityResolverContext $resolverContext, FieldConfig $config): ?Criteria
    {
        if (!$field = $this->resolveDefinitionField($resolverContext->getDefinition(), $config->getValue())) {
            return null;
        }

        $key = null;
        $refDef = null;

        // resolve reverse side to fetch data afterwards
        if ($field instanceof ManyToManyAssociationField) {
            $key = $this->getKeyByManyToMany($field);
            $refDef = $field->getReferenceDefinition();
        } elseif ($field instanceof OneToManyAssociationField) {
            $key = $this->getKeyByOneToMany($field);
            $refDef = $field->getReferenceClass();
        }

        if (!$key || !$refDef) {
            return null;
        }

        $key = $refDef::getEntityName() . '.' . $key;

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter($key, $resolverContext->getEntity()->getUniqueIdentifier())
        );

        return $criteria;
    }

    private function getKeyByManyToMany(ManyToManyAssociationField $field): ?string
    {
        $referenceClass = $field->getReferenceClass();

        /** @var ManyToManyAssociationField|null $manyToMany */
        $manyToMany = $field->getReferenceDefinition()::getFields()
            ->filterInstance(ManyToManyAssociationField::class)
            ->filter(function (ManyToManyAssociationField $field) use ($referenceClass) {
                return $field->getReferenceClass() === $referenceClass;
            })
            ->first();

        if (!$manyToMany) {
            return null;
        }

        return $manyToMany->getPropertyName() . '.' . $manyToMany->getReferenceField();
    }

    private function getKeyByOneToMany(OneToManyAssociationField $field): ?string
    {
        $referenceClass = $field->getReferenceClass();

        /** @var ManyToOneAssociationField|null $manyToOne */
        $manyToOne = $field->getReferenceClass()::getFields()
            ->filterInstance(ManyToOneAssociationField::class)
            ->filter(function (ManyToOneAssociationField $field) use ($referenceClass) {
                return $field->getReferenceClass() === $referenceClass;
            })
            ->first()
        ;

        if (!$manyToOne) {
            return null;
        }

        return $manyToOne->getPropertyName() . '.' . $manyToOne->getReferenceField();
    }
}
