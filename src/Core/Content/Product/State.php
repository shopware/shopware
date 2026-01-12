<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use ProductDefinition::TYPE_DIGITAL and ProductDefinition::TYPE_PHYSICAL instead
 */
#[Package('inventory')]
final class State
{
    public const IS_PHYSICAL = 'is-physical';
    public const IS_DOWNLOAD = 'is-download';
}
