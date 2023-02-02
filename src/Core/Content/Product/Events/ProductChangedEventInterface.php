<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

interface ProductChangedEventInterface
{
    public function getIds(): array;
}
