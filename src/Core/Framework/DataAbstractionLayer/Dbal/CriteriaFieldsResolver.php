<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class CriteriaFieldsResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(Criteria $criteria, EntityDefinition $definition): array
    {
        if (empty($criteria->getFields())) {
            return [];
        }

        $mapped = [];

        $fields = \array_merge($criteria->getFields(), $this->resolveRuntimeField($criteria, $definition));

        foreach ($fields as $accessor) {
            $field = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $accessor);

            if (isset($field[0]) && $field[0] instanceof AssociationField) {
                $criteria->addAssociation($accessor);
            }

            $pointer = &$mapped;
            foreach (explode('.', $accessor) as $part) {
                // @phpstan-ignore-next-line
                if (!isset($pointer[$part])) {
                    $pointer[$part] = [];
                }

                $pointer = &$pointer[$part];
            }
        }

        return $mapped;
    }

    /**
     * @return string[]
     */
    private function resolveRuntimeField(Criteria $criteria, EntityDefinition $definition): array
    {
        $mapped = [];

        foreach ($criteria->getFields() as $field) {
            $fields = EntityDefinitionQueryHelper::getFieldsOfAccessor($definition, $field);

            if (empty($fields)) {
                continue;
            }

            $accessor = [];
            foreach ($fields as $part) {
                $flag = $part->getFlag(Runtime::class);

                if ($flag instanceof Runtime) {
                    foreach ($flag->getDepends() as $depend) {
                        $mapped[] = implode('.', [...$accessor, ...[$depend]]);
                    }
                }

                if (!$part instanceof AssociationField) {
                    continue;
                }

                $accessor[] = $part->getPropertyName();
            }
        }

        return $mapped;
    }
}
