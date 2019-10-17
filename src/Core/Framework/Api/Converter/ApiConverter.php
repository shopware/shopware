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

        return array_key_exists($fieldName, $this->getNewFields()[$entityName] ?? []);
    }

    public function convert(string $entityName, array $payload): array
    {
        foreach ($this->getNewFields()[$entityName] ?? [] as $field => $converterFn) {
            if (!is_callable($converterFn)) {
                continue;
            }

            $payload = $converterFn($payload);
        }

        return $payload;
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
