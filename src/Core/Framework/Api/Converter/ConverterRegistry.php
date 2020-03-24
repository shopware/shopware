<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

class ConverterRegistry
{
    /**
     * @var array[int]ApiConverter[]
     */
    private $converters;

    /**
     * @var DefaultApiConverter
     */
    private $defaultApiConverter;

    public function __construct(iterable $converters, DefaultApiConverter $defaultApiConverter)
    {
        $this->defaultApiConverter = $defaultApiConverter;

        /** @var ApiConverter $converter */
        foreach ($converters as $converter) {
            $this->converters[$converter->getApiVersion()][] = $converter;
        }
    }

    public function isDeprecated(int $apiVersion, string $entityName, ?string $fieldName = null): bool
    {
        if ($this->defaultApiConverter->isDeprecated($apiVersion, $entityName, $fieldName)) {
            return true;
        }

        /** @var ApiConverter $converter */
        foreach ($this->converters[$apiVersion] ?? [] as $converter) {
            if ($converter->isDeprecated($entityName, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    public function isFromFuture(int $apiVersion, string $entityName, ?string $fieldName = null): bool
    {
        /** @var ApiConverter $converter */
        foreach ($this->converters[$apiVersion + 1] ?? [] as $converter) {
            if ($converter->isFromFuture($entityName, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    public function convert(int $apiVersion, string $entityName, array $payload): array
    {
        $payload = $this->defaultApiConverter->convert($apiVersion, $entityName, $payload);

        /** @var ApiConverter $converter */
        foreach ($this->converters[$apiVersion + 1] ?? [] as $converter) {
            $payload = $converter->convert($entityName, $payload);
        }

        return $payload;
    }

    public function getConverters(?int $apiVersion): array
    {
        if ($apiVersion) {
            return $this->converters[$apiVersion] ?? [];
        }

        return $this->converters;
    }
}
