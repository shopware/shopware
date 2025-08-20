<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CookieEntry>
 */
#[Package('framework')]
class CookieEntryCollection extends Collection
{
    public function merge(self $cookieEntryCollection): self
    {
        return $this->createNew(array_merge($this->elements, $cookieEntryCollection->getElements()));
    }

    protected function getExpectedClass(): ?string
    {
        return CookieEntry::class;
    }
}
