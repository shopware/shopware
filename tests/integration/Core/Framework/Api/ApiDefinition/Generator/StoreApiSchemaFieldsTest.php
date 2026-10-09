<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\ApiDefinition\Generator;

use OpenApi\Annotations\Property;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * @internal
 */
#[Package('framework')]
class StoreApiSchemaFieldsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServedSchemasDocumentEveryStoreApiField(): void
    {
        $schemaBuilder = static::getContainer()->get(OpenApiDefinitionSchemaBuilder::class);
        $spec = static::getContainer()->get(DefinitionService::class)->generate(
            'openapi-3',
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON
        );
        $servedSchemas = $spec['components']['schemas'] ?? [];

        $undocumented = [];

        foreach (static::getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions() as $definition) {
            $schemaName = $schemaBuilder->getSchemaName($definition);

            // the generator serves only the entities the Store API schema references
            if (!isset($servedSchemas[$schemaName])) {
                continue;
            }

            $generated = $schemaBuilder->getSchemaByDefinition(
                $definition,
                '/' . $definition->getEntityName(),
                true,
                true,
                DefinitionService::TYPE_JSON
            );
            $properties = $generated[$schemaName]->properties;
            $documented = $this->getDocumentedProperties($servedSchemas[$schemaName]);

            foreach (\is_array($properties) ? $properties : [] as $property) {
                static::assertInstanceOf(Property::class, $property);

                if (!\array_key_exists($property->property, $documented)) {
                    $undocumented[] = $schemaName . '.' . $property->property;
                }
            }
        }

        static::assertSame(
            [],
            $undocumented,
            'The Store API returns these fields, but its OpenAPI schema does not document them. Add them to the matching JSON schema in src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/components/schemas.'
        );
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<array-key, mixed> the documented properties, keyed by name
     */
    private function getDocumentedProperties(array $schema): array
    {
        $properties = $schema['properties'] ?? [];

        foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
            foreach ($schema[$keyword] ?? [] as $subSchema) {
                $properties += $this->getDocumentedProperties($subSchema);
            }
        }

        return $properties;
    }
}
