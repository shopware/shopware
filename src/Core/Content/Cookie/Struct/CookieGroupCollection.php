<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * Collection of {@see CookieGroup} indexed by the group's snippet name
 *
 * @extends Collection<CookieGroup>
 */
#[Package('framework')]
class CookieGroupCollection extends Collection
{
    public function set($key, $element): void
    {
        parent::set($element->snippetKeyName, $element);
    }

    public function add($element): void
    {
        $this->validateType($element);

        parent::set($element->snippetKeyName, $element);
    }

    public function getApiAlias(): string
    {
        return 'cookie_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CookieGroup::class;
    }
}
