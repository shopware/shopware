<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class InvalidateProductCache implements ProductChangedEventInterface
{
    /**
     * @param string[] $ids
     */
    public function __construct(private readonly array $ids, public readonly bool $force = false)
    {
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
