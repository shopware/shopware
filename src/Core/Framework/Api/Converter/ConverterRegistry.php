<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

/**
 * @package core
 */
class ConverterRegistry
{
    /**
     * @var iterable<ApiConverter>
     */
    private iterable $converters;

    /**
     * @internal
     *
     * @param iterable<ApiConverter> $converters
     */
    public function __construct(iterable $converters)
    {
        $this->converters = $converters;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function convert(string $entityName, array $payload): array
    {
        foreach ($this->converters as $converter) {
            $payload = $converter->convert($entityName, $payload);
        }

        return $payload;
    }

    /**
     * @return iterable<ApiConverter>
     */
    public function getConverters(): iterable
    {
        return $this->converters;
    }
}
