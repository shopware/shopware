<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @phpstan-type Api DefinitionService::API|DefinitionService::STORE_API
 * @phpstan-type ApiType DefinitionService::TypeJsonApi|DefinitionService::TypeJson
 * @phpstan-type OpenApiSpec  array{paths: array<string,array<mixed>>, components: array{schemas: array<string, array<mixed>>}}
 * @phpstan-type ApiSchema array<string, array{name: string, translatable: list<string>, properties: array<string, mixed>}|array{entity: string, properties: array<string, mixed>, write-protected: bool, read-protected: bool}>
 */
#[Package('core')]
class DefinitionService
{
    final public const API = 'api';
    final public const STORE_API = 'store-api';

    final public const TypeJsonApi = 'jsonapi';
    final public const TypeJson = 'json';

    /**
     * @var ApiDefinitionGeneratorInterface[]
     */
    private readonly array $generators;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        ApiDefinitionGeneratorInterface ...$generators
    ) {
        $this->generators = $generators;
    }

    /**
     * @phpstan-param Api $type
     * @phpstan-param ApiType $apiType
     *
     * @return OpenApiSpec
     */
    public function generate(string $format = 'openapi-3', string $type = self::API, string $apiType = self::TypeJsonApi): array
    {
        return $this->getGenerator($format, $type)->generate($this->getDefinitions($type), $type, $apiType);
    }

    /**
     * @phpstan-param Api $type
     *
     * @return ApiSchema
     */
    public function getSchema(string $format = 'openapi-3', string $type = self::API): array
    {
        return $this->getGenerator($format, $type)->getSchema($this->getDefinitions($type));
    }

    /**
     * @return ApiType|null
     */
    public function toApiType(string $apiType): ?string
    {
        if ($apiType !== self::TypeJsonApi && $apiType !== self::TypeJson) {
            return null;
        }

        return $apiType;
    }

    /**
     * @throws ApiDefinitionGeneratorNotFoundException
     */
    private function getGenerator(string $format, string $type): ApiDefinitionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format, $type)) {
                return $generator;
            }
        }

        throw new ApiDefinitionGeneratorNotFoundException($format);
    }

    /**
     * @throws ApiDefinitionGeneratorNotFoundException
     *
     * @return array<string, EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface>
     */
    private function getDefinitions(string $type): array
    {
        if ($type === self::API) {
            return $this->definitionRegistry->getDefinitions();
        }

        if ($type === self::STORE_API) {
            return $this->salesChannelDefinitionRegistry->getDefinitions();
        }

        throw new ApiDefinitionGeneratorNotFoundException($type);
    }
}
