<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Util\ArrayNormalizer;

class ExportMapper implements MapperInterface
{
    public function map(array $entityData, FieldDefinitionCollection $fieldDefinitions, EntityDefinition $entityDefinition): array
    {
        $result = [];
        $entityData = ArrayNormalizer::flatten($entityData);

        /** @var FieldDefinition $definition */
        foreach ($fieldDefinitions as $definition) {
            if (!array_key_exists($definition->getEntityField(), $entityData)) {
                continue;
            }

            $substitutions = $definition->getValueSubstitutions();
            $inputValue = $entityData[$definition->getEntityField()];
            $substitutionValue = null;
            if ($inputValue !== null) {
                $index = array_search($inputValue, $substitutions, true);
                if ($index !== false) {
                    $substitutionValue = $index;
                }
            }

            $result[$definition->getFileField()] = $substitutionValue ?? $inputValue;
        }

        return $result;
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_EXPORT;
    }
}
