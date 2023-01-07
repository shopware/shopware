<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

/**
 * @package core
 */
interface EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void;
}
