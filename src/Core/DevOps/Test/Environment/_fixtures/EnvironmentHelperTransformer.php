<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\Environment\_fixtures;

use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerData;
use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class EnvironmentHelperTransformer implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        $data->setValue($data->getValue() !== null ? $data->getValue() . ' bar' : null);
        $data->setDefault($data->getDefault() . ' baz');
    }
}
