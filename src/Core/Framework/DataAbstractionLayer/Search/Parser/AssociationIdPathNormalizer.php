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

        // Only paths ending in `<association>.id` can be shortened
        if (\count($parts) < 2 || array_pop($parts) !== 'id') {
            return $fieldName;
        }

        // `id` is popped above; pop the association too, so `$parts` keeps only the parent path
        $association = array_pop($parts);
        if ($association === null) {
            return $fieldName;
        }

        $candidate = $parts === []
            ? $association . 'Id'
            : implode('.', $parts) . '.' . $association . 'Id';

        // No `<association>Id` foreign key next to the association, e.g. to-many associations
        $fkField = EntityDefinitionQueryHelper::getField($candidate, $definition, $definition->getEntityName());
        if (!$fkField instanceof FkField) {
            return $fieldName;
        }

        // Only to-one associations hold the referenced id in a local column
        $associationField = EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $candidate)->getFields()->get($association);
        if (!$associationField instanceof ManyToOneAssociationField && !$associationField instanceof OneToOneAssociationField) {
            return $fieldName;
        }

        // The foreign key belongs to another association, or the one-to-one stores its key on the other side
        if ($associationField->getStorageName() !== $fkField->getStorageName()) {
            return $fieldName;
        }

        // The foreign key references another column than `id`, e.g. `#[ManyToOne(ref: 'technical_name')]`
        if ($associationField->getReferenceField() !== 'id') {
            return $fieldName;
        }

        // With inheritance, the join matches the parent's rows too, which the foreign key alone does not
        if ($associationField->is(ReverseInherited::class)) {
            return $fieldName;
        }

        return $candidate;
    }
}
