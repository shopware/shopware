<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

class ConverterRegistry
{
    /**
     * @var array[int]array[string]ApiConverterInterface
     */
    private $currentConverters;

    /**
     * @var array[int]array[string]ApiConverterInterface
     */
    private $legacyConverters;

    public function __construct(iterable $converters)
    {
        /** @var ApiConverterInterface $converter */
        foreach ($converters as $converter) {
            $this->currentConverters[$converter->getDeprecatedApiVersion()][$converter->getProcessedEntityName()][] = $converter;
            $this->legacyConverters[$converter->getDeprecatedApiVersion() - 1][$converter->getProcessedEntityName()][] = $converter;
        }
    }

    /**
     * @return ApiConverterInterface[]
     */
    public function getCurrentConverters(string $entity, int $apiVersion): array
    {
        return $this->currentConverters[$apiVersion][$entity] ?? [];
    }

    /**
     * @return ApiConverterInterface[]
     */
    public function getLegacyConverters(string $entity, int $apiVersion): array
    {
        return $this->legacyConverters[$apiVersion][$entity] ?? [];
    }
}
