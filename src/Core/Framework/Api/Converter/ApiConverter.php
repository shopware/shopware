<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

abstract class ApiConverter
{
    /**
     * Returns the ApiVersion this converter handles
     */
    abstract public function getApiVersion(): int;

    public function isDeprecated(string $entityName, ?string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return array_key_exists($entityName, $this->getDeprecations()) && !is_array($this->getDeprecations()[$entityName]);
        }

        return \in_array($fieldName, $this->getDeprecations()[$entityName] ?? [], true);
    }

    public function isFromFuture(string $entityName, ?string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return array_key_exists($entityName, $this->getNewFields()) && !is_array($this->getNewFields()[$entityName]);
        }

        return \in_array($fieldName, $this->getNewFields()[$entityName] ?? [], true);
    }

    public function convert(string $entityName, array $payload): array
    {
        $converterFns = $this->getConverterFunctions();
        if (array_key_exists($entityName, $converterFns)) {
            $payload = $converterFns[$entityName]($payload);
        }

        return $payload;
    }

    /**
     * Returns the deprecations introduced in this Api version
     * The array key is the entity and the values the fields that are deprecated
     * A "true" value will indicate that the whole entity was deprecated, e.g.
     * [
     *      'deprecated' => [
     *          'field1',
     *          'field2'
     *      ],
     *      'wholeEntityDeprecated' => true
     * ]
     */
    abstract protected function getDeprecations(): array;

    /**
     * Returns the new fields introduced in this Api version
     * The array key is the entity and the values the fields that are introduced
     * A "true" value will indicate that the whole entity was introduced, e.g.
     * [
     *      'new' => [
     *          'field1',
     *          'field2'
     *      ],
     *      'wholeEntityNew' => true
     * ]
     */
    abstract protected function getNewFields(): array;

    /**
     * Returns the function to convert the entities
     * The function are indexed by entityName that they handle and get the write payload as parameter and should return the converted payload, e.g.
     * [
     *      'product' => function (array $payload): array {
     *          // convert payload
     *          return $payload;
     *      }
     * ]
     *
     * @return callable[]
     */
    abstract protected function getConverterFunctions(): array;
}
