<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Util\ArrayNormalizer;

class ImportMapper implements MapperInterface
{
    public function map(array $fileData, FieldDefinitionCollection $fieldDefinitions, EntityDefinition $entityDefinition): array
    {
        $flattened = [];

        /** @var FieldDefinition $definition */
        foreach ($fieldDefinitions as $definition) {
            if (!array_key_exists($definition->getFileField(), $fileData)) {
                continue;
            }

            $substitutions = $definition->getValueSubstitutions();
            $inputValue = $fileData[$definition->getFileField()];
            $substitutionValue = null;
            if ($inputValue !== null) {
                $substitutionValue = $substitutions[$inputValue] ?? null;
            }

            $flattened[$definition->getEntityField()] = $substitutionValue ?? $inputValue;
        }

        $nestedData = ArrayNormalizer::expand($flattened);

        $parser = new FieldValueParser($entityDefinition);
        foreach ($nestedData as $key => $value) {
            $field = $entityDefinition->getFields()->get($key);
            $nestedData[$key] = $parser->parse($field, $value);
        }

        return $nestedData;
    }

    public function supports(ImportExportLogEntity $logEntity): bool
    {
        return $logEntity->getActivity() === ImportExportLogEntity::ACTIVITY_IMPORT;
    }
}
