<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

class ImportMapper implements MapperInterface
{
    /**
     * @var FieldDefinitionCollection
     */
    private $definitions;

    public function __construct(FieldDefinitionCollection $definitions)
    {
        $this->definitions = $definitions;
    }

    public function map(array $fileData): array
    {
        $result = [];

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

            $result[$definition->getEntityField()] = $substitutionValue ?? $inputValue;
        }

        return self::expand($result);
    }

    private static function expand(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (strpos($key, '.') !== false) {
                $first = strstr($key, '.', true);
                $rest = strstr($key, '.');
                if (isset($result[$first])) {
                    $result[$first] = array_merge($result[$first], self::expand([substr($rest, 1) => $value]));
                } else {
                    $result[$first] = self::expand([substr($rest, 1) => $value]);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
