<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

class ConverterRegistry
{
    /**
     * @var iterable
     */
    private $converters;

    /**
     * @var DefaultApiConverter
     */
    private $defaultApiConverter;

    public function __construct(iterable $converters, DefaultApiConverter $defaultApiConverter)
    {
        $this->defaultApiConverter = $defaultApiConverter;
        $this->converters = $converters;
    }

    public function convert(string $entityName, array $payload): array
    {
        $payload = $this->defaultApiConverter->convert($entityName, $payload);

        /** @var ApiConverter $converter */
        foreach ($this->converters as $converter) {
            $payload = $converter->convert($entityName, $payload);
        }

        return $payload;
    }

    public function getConverters(): iterable
    {
        return $this->converters;
    }
}
