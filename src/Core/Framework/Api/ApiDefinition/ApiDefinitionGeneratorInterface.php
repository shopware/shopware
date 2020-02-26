<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

/**
 * @internal
 */
interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format, int $version, string $api): bool;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function generate(array $definitions, int $version, string $api): array;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function getSchema(array $definitions, int $version): array;
}
