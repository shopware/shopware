<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CookieEntry extends CookieStruct
{
    public function __construct(
        public bool $hidden = false,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cookie_entry';
    }
}
