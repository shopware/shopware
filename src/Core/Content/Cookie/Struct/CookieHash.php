<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class CookieHash extends Struct
{
    public function __construct(
        public readonly string $cookieHash,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cookie_hash';
    }
}
