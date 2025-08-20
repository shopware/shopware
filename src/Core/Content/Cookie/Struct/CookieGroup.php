<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CookieGroup extends CookieStruct
{
    public function __construct(
        public bool $isRequired,
        public CookieEntryCollection $entries = new CookieEntryCollection(),
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cookie_group';
    }
}
