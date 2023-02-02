<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\Environment\_fixtures;

use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerData;
use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;

/**
 * @internal
 */
class EnvironmentHelperTransformer2 implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        $data->setValue($data->getValue() !== null ? $data->getValue() . ' baz' : null);
    }
}
