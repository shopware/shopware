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

        return array_key_exists($fieldName, $this->getDeprecations()[$entityName] ?? []);
    }

    public function isFromFuture(string $entityName, ?string $fieldName = null): bool
    {
        if ($fieldName === null) {
            return array_key_exists($entityName, $this->getNewFields()) && !is_array($this->getNewFields()[$entityName]);
        }

        return \in_array($fieldName, $this->getNewFields()[$entityName] ?? [], true);
    }

    public function convertField(string $entityName, string $fieldName, array $payload): array
    {
        if (!$this->isDeprecated($entityName, $fieldName) || !\is_callable($this->getDeprecations()[$entityName][$fieldName])) {
            return $payload;
        }

        $converterFn = $this->getDeprecations()[$entityName][$fieldName];

        return $converterFn($payload);
    }

    /**
     * Returns the deprecations introduced in this Api version
     */
    abstract protected function getDeprecations(): array;

    /**
     * Returns the new fields introduced in this Api version
     */
    abstract protected function getNewFields(): array;
}
