<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritanceCircularReference;

use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Framework\ThemeInterface;

/**
 * @internal
 */
class ThemeWithMultiInheritanceCircularReference extends Plugin implements ThemeInterface
{
}
