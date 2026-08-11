<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;
use Shopware\Core\System\CustomEntity\Xml\Field\AssociationField;
use Shopware\Core\System\CustomEntity\Xml\Field\Field;
use Shopware\Core\System\CustomEntity\Xml\Field\OneToManyField;
use Shopware\Core\System\CustomEntity\Xml\Field\StringField;

/**
 * @internal
 */
#[Package('framework')]
class CustomEntityXmlSchemaValidator
{
    public function __construct(private readonly CustomEntityNameValidator $nameValidator)
    {
    }

    public function validate(CustomEntityXmlSchema $schema): void
    {
        if ($schema->getEntities() === null) {
            throw new \RuntimeException('No entities found in parsed xml file');
        }

        foreach ($schema->getEntities()->getEntities() as $entity) {
            $this->validateNames($entity);

            if ($entity->isCustomFieldsAware()) {
                $label = $entity->getLabelProperty();

                if ($label === null) {
                    throw CustomEntityException::noLabelProperty();
                }

                if (!$entity->hasField($label)) {
                    throw CustomEntityException::labelPropertyNotDefined($label);
                }

                if (!$entity->getField($label) instanceof StringField) {
                    throw CustomEntityException::labelPropertyWrongType($label);
                }
            }

            foreach ($entity->getFields() as $field) {
                if ($field instanceof OneToManyField) {
                    $this->validateAssociation($field);
                }
            }
        }
    }

    /**
     * Reject names that cannot be safely used as SQL identifiers before they are persisted,
     * so a bad manifest fails at install time instead of during the schema update.
     */
    private function validateNames(Entity $entity): void
    {
        $this->nameValidator->validate(
            $entity->getName(),
            array_map(static fn (Field $field): string => $field->getName(), $entity->getFields())
        );
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
