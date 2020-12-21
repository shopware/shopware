<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

abstract class ApiConverter
{
    public function convert(string $entityName, array $payload): array
    {
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
     * @return callable[]
     */
    abstract protected function getConverterFunctions(): array;
}
