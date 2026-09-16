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
    public function __construct(
        public mixed $value,
        public string $removedIn,
        public string $message,
    ) {
    }

    public static function resolve(mixed $value): mixed
    {
        if (!$value instanceof self) {
            return $value;
        }

        Feature::triggerDeprecationOrThrow($value->removedIn, $value->message);

        return $value->value;
    }
}
