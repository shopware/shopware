<?php declare(strict_types=1);

namespace Shopware\Rest\ApiDefinition;

interface ApiDefinitionGeneratorInterface
{
    public function supports(string $format): bool;

    public function generate(): array;

    public function getSchema(): array;
}
