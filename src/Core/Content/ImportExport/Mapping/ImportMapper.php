<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Util\ArrayConverter;

class ImportMapper implements MapperInterface
{
    /**
     * @var FieldDefinitionCollection
     */
    private $definitions;

    /**
     * @var EntityDefinition
     */
    private $entityDefinition;

    public function __construct(FieldDefinitionCollection $definitions, EntityDefinition $entityDefinition)
    {
        $this->definitions = $definitions;
        $this->entityDefinition = $entityDefinition;
    }

    public function map(array $fileData): array
    {
        $flattened = [];

        /** @var FieldDefinition $definition */
        foreach ($this->definitions as $definition) {
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

        $nestedData = ArrayConverter::expand($flattened);

        $parser = new FieldValueParser($this->entityDefinition);
        foreach ($nestedData as $key => $value) {
            $field = $this->entityDefinition->getFields()->get($key);
            $nestedData[$key] = $parser->parse($field, $value);
        }

        return $nestedData;
    }
}
