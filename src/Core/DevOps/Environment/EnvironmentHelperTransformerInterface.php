<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

interface EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void;
}
