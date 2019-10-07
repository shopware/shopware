<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

class ConverterRegistry
{
    /**
     * @var ApiConverter[]
     */
    private $converters;

    public function __construct(iterable $converters)
    {
        /** @var ApiConverter $converter */
        foreach ($converters as $converter) {
            $this->converters[$converter->getApiVersion()] = $converter;
        }
    }

    public function getDeprecationConverter(int $apiVersion): ApiConverter
    {
        return $this->converters[$apiVersion] ?? new NullApiConverter();
    }

    public function getFutureConverter(int $apiVersion): ApiConverter
    {
        return $this->converters[$apiVersion + 1] ?? new NullApiConverter();
    }
}
