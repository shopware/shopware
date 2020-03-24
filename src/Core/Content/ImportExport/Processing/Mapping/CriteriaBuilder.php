<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaBuilder
{
    /**
     * @var EntityDefinition
     */
    private $definition;

    public function __construct(EntityDefinition $entityDefinition)
    {
        $this->definition = $entityDefinition;
    }

    public function enrichCriteria(Config $config, Criteria $criteria): Criteria
    {
        foreach ($config->getMapping() as $mapping) {
            $tmpDefinition = $this->definition;
            $parts = explode('.', $mapping->getKey());

            $prefix = '';

            foreach ($parts as $assoc) {
                $field = $tmpDefinition->getField($assoc);
                if (!$field || !$field instanceof AssociationField) {
                    break;
                }
                $criteria->addAssociation($prefix . $assoc);
                $prefix .= $assoc . '.';
                $tmpDefinition = $field->getReferenceDefinition();
            }
        }

        return $criteria;
    }
}
