<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Reversible\_fixtures\SwagNoMigrations;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;

/**
 * A plugin without a Migration directory.
 *
 * @internal
 */
#[Package('framework')]
class SwagNoMigrations extends Plugin
{
}
