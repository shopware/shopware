<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;

interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format): bool;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function generate(array $definitions): array;

    /**
     * @param EntityDefinition[]|SalesChannelDefinitionInterface[] $definitions
     */
    public function getSchema(array $definitions): array;
}
