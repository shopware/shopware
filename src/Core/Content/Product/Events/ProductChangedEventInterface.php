<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Log\Package;
/**
 * @package inventory
 */
#[Package('inventory')]
interface ProductChangedEventInterface
{
    public function getIds(): array;
}
