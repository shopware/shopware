<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 * @phpstan-import-type Api from DefinitionService
 * @phpstan-import-type ApiType from DefinitionService
 * @phpstan-import-type OpenApiSpec from DefinitionService
 * @phpstan-import-type ApiSchema from DefinitionService
 */
interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format, string $api): bool;

    /**
     * @param list<EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     * @phpstan-param  Api $api
     * @phpstan-param ApiType $apiType
     *
     * @return OpenApiSpec
     */
    public function generate(array $definitions, string $api, string $apiType): array;

    /**
     * @param list<EntityDefinition>|list<EntityDefinition&SalesChannelDefinitionInterface> $definitions
     *
     * @return ApiSchema
     */
    public function getSchema(array $definitions): array;
}
