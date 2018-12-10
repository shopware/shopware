<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;

/**
 * Resolves the Order of the Entities which will be written in accordance to the Entity-Dependencies
 */
class EntityWriteOrderResolver
{
    /**
     * Resolve and reorder Dependencies (First Level dependencies for now)
     *
     * @param string|EntityDefinition $entityDefinition
     *
     * @return array
     */
    public static function resolveDependencies(string $entityDefinition): array
    {
        $associations = self::getAssociations($entityDefinition);

        $manyToOne = self::filterAssociationReferences($entityDefinition, ManyToOneAssociationField::class, $associations);

        $oneToMany = self::filterAssociationReferences($entityDefinition, OneToManyAssociationField::class, $associations);

        $manyToMany = self::filterAssociationReferences($entityDefinition, ManyToManyAssociationField::class, $associations);

        $self = array_filter([$entityDefinition, $entityDefinition::getTranslationDefinitionClass()]);

        /*
         * If a linked entity exists once as OneToMany but also as ManyToOne (bi-directional foreign keys),
         * it must be treated as OneToMany. In the MySQL database,
         * no foreign key may be created for the ManyToOne relation.
         *
         * Examples:
         *      a customer has 1:N addresses
         *      a customer has 1:1 default_shipping_address
         *      a customer has 1:1 default_billing_address
         */
        $c = array_intersect($manyToOne, $oneToMany);
        foreach ($c as $index => $value) {
            unset($manyToOne[$index]);
        }

        //Copy working Arrays
        $oneToManyReorder = $oneToMany;
        $oneToManyTemp = $oneToMany;

        /** @var string $referenceDefinition */
        foreach ($oneToMany as $index => $referenceDefinition) {
            //get mapped Associations from Nested Entity (only OneToMany needed here)
            $associations = self::getAssociations($referenceDefinition);
            $elements = $associations->filterInstance(OneToManyAssociationField::class)->getElements();
            $assocs = self::mapAssociations($entityDefinition, $elements);

            //get Dependencies from mapped Associations
            $dependencies = self::cleanCircleDependencies(
                $entityDefinition, self::getDependencies($referenceDefinition, $assocs)
            );

            //resort: if a Dependency is Referenced inside a Main Reference push it above
            /** @var EntityDefinition $referenceDefinitionInside */
            foreach ($oneToManyTemp as $indexInside => $referenceDefinitionInside) {
                if ($indexInside === $index || $index > $indexInside) {
                    continue;
                }
                if (in_array($referenceDefinitionInside, $dependencies, true)) {
                    $unsetIndex = array_search($referenceDefinition, $oneToManyReorder, true);
                    unset($oneToManyReorder[$unsetIndex]);
                    array_splice($oneToManyReorder, $indexInside, 0, $oneToMany[$index]);
                }
            }
            $oneToManyTemp = $oneToManyReorder;
        }

        return array_unique(array_values(array_merge($manyToOne, $self, $oneToManyReorder, $manyToMany)));
    }

    private static function getAssociations(string $entityDefinition): FieldCollection
    {
        /* @var entityDefinition|string $entityDefinition */
        return $entityDefinition::getFields()->filter(function (Field $field) {
            return $field instanceof AssociationInterface && !$field->is(ReadOnly::class);
        });
    }

    private static function filterAssociationReferences(
        string $entityDefinition, string $type, FieldCollection $fields
    ): array {
        $associations = $fields->filterInstance($type)->getElements();

        return array_values(self::mapAssociations($entityDefinition, $associations));
    }

    /**
     * returns an array of the referenced Classes for each Association
     * [
     *  [media] => \Product\Aggregate\ProductMedia\ProductMediaDefinition
     *  [priceRules] => \Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition
     * ]
     *
     * @param string $entityDefinition
     * @param array  $associations
     *
     * @return array
     */
    private static function mapAssociations(string $entityDefinition, array $associations): array
    {
        $associations = array_map(function (AssociationInterface $association) use ($entityDefinition) {
            if ($association->getReferenceClass() !== $entityDefinition) {
                return $association->getReferenceClass();
            }

            return null;
        }, $associations);

        return array_filter($associations);
    }

    /**
     * Get Dependencies of Associations
     * Determine all linked Entities by all the Associations
     *
     * @param string $entityDefinition
     * @param array  $associations
     *
     * @return array
     */
    private static function getDependencies(string $entityDefinition, array $associations): array
    {
        $nestedAssociations = [];
        /** @var EntityDefinition|string $association */
        foreach ($associations as $key => $association) {
            $elements = self::getAssociations($association)->getElements();

            /** @var Field $nestedAssoc */
            foreach (self::mapAssociations($entityDefinition, $elements) as $nestedAssoc) {
                $nestedAssociations[] = $nestedAssoc;
            }
        }

        return array_unique($nestedAssociations);
    }

    /**
     * Clean self-referenced Dependencies
     *
     * @param string $entityDefinition
     * @param array  $associations
     *
     * @return array
     */
    private static function cleanCircleDependencies(string $entityDefinition, array $associations): array
    {
        return array_filter($associations, function ($value) use ($entityDefinition) {
            return $value !== $entityDefinition;
        });
    }
}
