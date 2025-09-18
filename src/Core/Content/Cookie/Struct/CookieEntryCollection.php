<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * Collection of {@see CookieEntry} indexed by the entry's cookie name
 *
 * @extends Collection<CookieEntry>
 */
#[Package('framework')]
class CookieEntryCollection extends Collection
{
    public function set($key, $element): void
    {
        parent::set($element->cookie, $element);
    }

    public function add($element): void
    {
        $this->validateType($element);

        parent::set($element->cookie, $element);
    }

    public function getApiAlias(): string
    {
        return 'cookie_entry_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return CookieEntry::class;
    }
}
