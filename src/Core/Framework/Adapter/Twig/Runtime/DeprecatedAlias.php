<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Runtime;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class DeprecatedAlias
{
    public function __construct(public mixed $value)
    {
    }

    public static function resolve(mixed $value, string $name): mixed
    {
        if (!$value instanceof self) {
            return $value;
        }

        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('The "%s" Twig variable is deprecated.', $name));

        return $value->value;
    }

    public function silentUnwrap(): mixed
    {
        return $this->value;
    }
}
