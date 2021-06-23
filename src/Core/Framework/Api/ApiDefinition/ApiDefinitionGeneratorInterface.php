<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 */
interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format, string $api): bool;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function generate(array $definitions, string $api, string $apiType): array;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function getSchema(array $definitions): array;
}
