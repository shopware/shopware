<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

/**
 * @package inventory
 */
interface ProductChangedEventInterface
{
    public function getIds(): array;
}
