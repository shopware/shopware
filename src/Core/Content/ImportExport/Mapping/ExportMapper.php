<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Framework\Util\ArrayNormalizer;

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
        $entityData = ArrayNormalizer::flatten($entityData);

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
}
