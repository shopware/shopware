<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Feature;

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

    /**
     * @internal
     */
    public function __construct(iterable $converters, DefaultApiConverter $defaultApiConverter)
    {
        $this->defaultApiConverter = $defaultApiConverter;
        $this->converters = $converters;
    }

    public function convert(string $entityName, array $payload): array
    {
        if (!Feature::isActive('v6.5.0.0')) {
            $payload = $this->defaultApiConverter->convert($entityName, $payload);
        }

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
