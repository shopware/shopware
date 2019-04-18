<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

trait SalesChannelDefinitionTrait
{
    protected static function decorateDefinitions(FieldCollection $fields): void
    {
        foreach ($fields as $field) {
            if (!$field instanceof AssociationField) {
                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $field->setReferenceDefinition(
                    $field->getReferenceDefinition()::getSalesChannelDecorationDefinition()
                );

                continue;
            }

            $field->setReferenceClass(
                $field->getReferenceClass()::getSalesChannelDecorationDefinition()
            );
        }
    }
}
