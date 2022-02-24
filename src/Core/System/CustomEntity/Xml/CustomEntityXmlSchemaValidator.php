<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToManyField;

/**
 * @internal
 */
class CustomEntityXmlSchemaValidator
{
    public function validate(CustomEntityXmlSchema $schema): void
    {
        if ($schema->getEntities() === null) {
            throw new HttpException('custom_entity_xml_validator.no_entities_found', 'No entities found in parsed xml file');
        }

        foreach ($schema->getEntities()->getEntities() as $entity) {
            if (empty($entity->getName())) {
                throw new HttpException('custom_entity_xml_validator.empty_name', 'Some of the entities has no configured name');
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
        if (\str_starts_with($reference, 'custom_entity_')) {
            return;
        }

        if ($field->getOnDelete() === AssociationField::CASCADE) {
            throw new HttpException('custom_entity_xml_validator.cascade_forbidden', \sprintf('Cascade delete and referencing core tables are not allowed, field %s', $field->getName()));
        }

        if ($field->isReverseRequired()) {
            throw new HttpException('custom_entity_xml_validator.reverse_forbidden', \sprintf('Reverse required when referencing core tables is not allowed, field %s', $field->getName()));
        }
    }
}
