<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToManyField;

/**
 * @internal
 */
#[Package('core')]
class CustomEntityXmlSchemaValidator
{
    public function validate(CustomEntityXmlSchema $schema): void
    {
        if ($schema->getEntities() === null) {
            throw new \RuntimeException('No entities found in parsed xml file');
        }

        foreach ($schema->getEntities()->getEntities() as $entity) {
            if (empty($entity->getName())) {
                throw new \RuntimeException('Some of the entities has no configured name');
            }

            foreach ($entity->getFields() as $field) {
                if ($field instanceof OneToManyField) {
                    $this->validateAssociation($field);
                }
            }
        }
    }

    private function validateAssociation(OneToManyField $field): void
    {
        $reference = $field->getReference();

        // reference on custom entity table
        if (\str_starts_with($reference, 'custom_entity_') || \str_starts_with($reference, 'ce_')) {
            return;
        }

        if ($field->getOnDelete() === AssociationField::CASCADE) {
            throw new \RuntimeException(\sprintf('Cascade delete and referencing core tables are not allowed, field %s', $field->getName()));
        }

        if ($field->isReverseRequired()) {
            throw new \RuntimeException(\sprintf('Reverse required when referencing core tables is not allowed, field %s', $field->getName()));
        }
    }
}
