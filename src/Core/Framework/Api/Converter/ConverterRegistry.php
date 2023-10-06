<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed as it is not used anymore
 */
#[Package('core')]
class ConverterRegistry
{
    /**
     * @internal
     *
     * @param iterable<ApiConverter> $converters
     */
    public function __construct(private readonly iterable $converters)
    {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function convert(string $entityName, array $payload): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

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
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $this->converters;
    }
}
