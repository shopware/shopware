<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed as it is unused
 */
#[Package('core')]
abstract class ApiConverter
{
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

        $converterFns = $this->getConverterFunctions();
        if (\array_key_exists($entityName, $converterFns)) {
            $payload = $converterFns[$entityName]($payload);
        }

        return $payload;
    }

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
     * @return array<string, callable(array<string, mixed>): array<string, mixed>>
     */
    abstract protected function getConverterFunctions(): array;
}
