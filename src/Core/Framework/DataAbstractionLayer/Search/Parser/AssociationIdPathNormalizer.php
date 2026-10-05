<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Parser;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class AssociationIdPathNormalizer
{
    public static function normalize(EntityDefinition $definition, string $fieldName): string
    {
        $parts = explode('.', $fieldName);

        if (\count($parts) < 2 || array_pop($parts) !== 'id') {
            return $fieldName;
        }

        $association = array_pop($parts);
        if ($association === null) {
            return $fieldName;
        }

        $candidate = $parts === []
            ? $association . 'Id'
            : implode('.', $parts) . '.' . $association . 'Id';

        $fkField = EntityDefinitionQueryHelper::getField($candidate, $definition, $definition->getEntityName());
        if (!$fkField instanceof FkField) {
            return $fieldName;
        }

        $associationField = EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $candidate)->getFields()->get($association);
        if (!$associationField instanceof ManyToOneAssociationField && !$associationField instanceof OneToOneAssociationField) {
            return $fieldName;
        }

        if ($associationField->getStorageName() !== $fkField->getStorageName() || $associationField->is(ReverseInherited::class)) {
            return $fieldName;
        }

        return $candidate;
    }
}
