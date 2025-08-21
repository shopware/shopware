<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CookieGroup extends Struct
{
    public bool $isRequired = false;

    public ?string $snippetDescription;

    public ?string $cookie;

    public ?string $value;

    public ?int $expiration;

    public ?CookieEntryCollection $entries;

    public function __construct(
        public string $snippetName,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'cookie_group';
    }
}
