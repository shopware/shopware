<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Single authority over the universal style option set: validation, introspection, and the
 * storefront/admin contract all read it, so they cannot drift.
 */
#[Package('framework')]
abstract class AbstractContentSystemStyleOptionRegistry
{
    abstract public function getDecorated(): AbstractContentSystemStyleOptionRegistry;

    /**
     * @return array<string, StyleOptionSpecification>
     */
    abstract public function all(): array;

    public function invalidate(): void
    {
        throw new DecorationPatternException(self::class);
    }
}
