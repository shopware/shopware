<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
abstract class CookieStruct extends Struct
{
    public ?string $snippetName;

    public ?string $snippetDescription;

    public ?string $cookie;

    public ?string $value;

    public ?string $expiration;
}
