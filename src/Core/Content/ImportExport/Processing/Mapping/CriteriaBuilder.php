<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('system-settings')]
class CriteriaBuilder
{
    public function __construct(private readonly EntityDefinition $definition)
    {
    }

    public function enrichCriteria(Config $config, Criteria $criteria): Criteria
    {
        foreach ($config->getMapping() as $mapping) {
            $tmpDefinition = $this->definition;
            $parts = explode('.', $mapping->getKey());

            $prefix = '';

            foreach ($parts as $assoc) {
                if ($assoc === 'extensions') {
                    continue; // extension associations must also be joined if the field is in the mapping
                }

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
