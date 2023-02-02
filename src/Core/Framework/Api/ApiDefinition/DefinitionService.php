<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @phpstan-type Api DefinitionService::API|DefinitionService::STORE_API
 * @phpstan-type ApiType DefinitionService::TypeJsonApi|DefinitionService::TypeJson
 * @phpstan-type OpenApiSpec  array{paths: array<string,array<mixed>>, components: array{schemas: array<string, array<mixed>>}}
 */
class DefinitionService
{
    public const API = 'api';
    public const STORE_API = 'store-api';

    public const TypeJsonApi = 'jsonapi';
    public const TypeJson = 'json';

    /**
     * @var ApiDefinitionGeneratorInterface[]
     */
    private $generators;

    private SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry;

    private DefinitionInstanceRegistry $definitionRegistry;

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
        ApiDefinitionGeneratorInterface ...$generators
    ) {
        $this->generators = $generators;
        $this->salesChannelDefinitionRegistry = $salesChannelDefinitionRegistry;
        $this->definitionRegistry = $definitionRegistry;
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
     * @return array<string, array{entity: string, properties: array<string, mixed>, write-protected: bool, read-protected: bool}|array{name: string, translatable: array<string, mixed>, properties: array<string, mixed>}>
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
     * @return list<EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface>
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
