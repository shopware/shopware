<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

class ExportMapper implements MapperInterface
{
    /**
     * @var FieldDefinitionCollection
     */
    private $definitions;

    public function __construct(FieldDefinitionCollection $definitions)
    {
        $this->definitions = $definitions;
    }

    public function map(array $entityData): array
    {
        $result = [];
        $entityData = self::flatten($entityData);

        /** @var FieldDefinition $definition */
        foreach ($this->definitions as $definition) {
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

    private static function flatten(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                foreach (self::flatten($value) as $innerKey => $innerValue) {
                    $result[$key . '.' . $innerKey] = $innerValue;
                }
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
