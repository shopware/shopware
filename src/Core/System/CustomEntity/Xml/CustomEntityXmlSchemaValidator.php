<?php

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToManyField;

/**
 * @internal
 */
class CustomEntityXmlSchemaValidator
{
    public function validate(CustomEntityXmlSchema $schema): void
    {
        if ($schema->getEntities() === null) {
            throw new HttpException('custom_entity_xml_no_entities_found', 400, 'No entities found in parsed xml file');
        }

        foreach ($schema->getEntities()->getEntities() as $entity) {
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
        if (\strpos($reference, 'custom_entity_') === 0) {
            return;
        }

        if ($field->getOnDelete() === 'cascade') {
            throw new HttpException('custom_entity_cascade_restricted', 400, \sprintf('Cascade delete and referencing core tables are not allowed, field %s', $field->getName()));
        }

        if ($field->isReverseRequired()) {
            throw new HttpException('custom_entity_required_restricted', 400, \sprintf('Reverse required when referencing core tables is not allowed, field %s', $field->getName()));
        }
    }
}