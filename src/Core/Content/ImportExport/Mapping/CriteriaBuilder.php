<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaBuilder
{
    /**
     * @var FieldDefinitionCollection
     */
    private $fieldDefinitions;

    /**
     * @var EntityDefinition
     */
    private $entityDefinition;

    public function __construct(FieldDefinitionCollection $fieldDefinitions, EntityDefinition $entityDefinition)
    {
        $this->fieldDefinitions = $fieldDefinitions;
        $this->entityDefinition = $entityDefinition;
    }

    public function build(): Criteria
    {
        $criteria = new Criteria();

        /** @var FieldDefinition $fieldDefinition */
        foreach ($this->fieldDefinitions as $fieldDefinition) {
            // Finds only direct associations by using the string until the first dot character
            $assocFieldName = mb_strstr($fieldDefinition->getEntityField(), '.', true);
            if (!is_string($assocFieldName)) {
                continue;
            }

            $field = $this->entityDefinition->getFields()->get($assocFieldName);
            if ($field instanceof AssociationField) {
                $criteria->addAssociation($field->getPropertyName());
            }
        }

        return $criteria;
    }
}
