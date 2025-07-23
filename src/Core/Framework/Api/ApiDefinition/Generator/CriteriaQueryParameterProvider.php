<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class CriteriaQueryParameterProvider
{
    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $parameters = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function getParameters(): array
    {
        if ($this->parameters !== null) {
            return $this->parameters;
        }

        return $this->parameters = $this->parseCriteriaFile();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseCriteriaFile(): array
    {
        $file = __DIR__ . '/Schema/StoreApi/components/schemas/Criteria.json';
        if (!is_file($file)) {
            throw ApiException::schemaDefinitionNotReadable($file);
        }

        try {
            /** @var array{components: array{schemas: array<string, array{properties: array<string, array<string, mixed>>}>}} $data */
            $data = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ApiException::invalidSchemaDefinitions($file, $e);
        }

        $properties = $data['components']['schemas']['Criteria']['properties'] ?? [];

        $parameters = [];
        foreach ($properties as $name => $property) {
            $parameter = [
                'name' => $name,
                'in' => 'query',
                'description' => $property['description'] ?? '',
                'schema' => [],
            ];

            if (isset($property['$ref'])) {
                $parameter['schema']['$ref'] = $property['$ref'];
                $parameter['style'] = 'deepObject';
                $parameter['explode'] = true;
            } elseif (isset($property['type'])) {
                $parameter['schema']['type'] = $property['type'];
                if ($property['type'] === 'array' || $property['type'] === 'object') {
                    $parameter['style'] = 'deepObject';
                    $parameter['explode'] = true;
                }
            } else {
                continue;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }
}
