<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;


use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format): bool;

    /**
     * @param EntityDefinition[] $definitions
     */
    public function generate(array $definitions): array;

    /**
     * @param EntityDefinition[] $definitions
     */
    public function getSchema(array $definitions): array;
}
