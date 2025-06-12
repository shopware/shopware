<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CookieGroup extends CookieStruct
{
    public function __construct(
        public bool $isRequired,
        /** @var list<CookieEntry> */
        public array $entries = [],
    ) {
    }

    /**
     * @return list<CookieEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @param list<CookieEntry> $entries
     */
    public function setEntries(array $entries): void
    {
        $this->entries = $entries;
    }

    public function getApiAlias(): string
    {
        return 'cookie_group';
    }
}
