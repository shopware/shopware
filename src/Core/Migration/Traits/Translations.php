<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class Translations
{
    /**
     * @param array<string, string|null> $german
     * @param array<string, string|null> $english
     */
    public function __construct(
        protected array $german,
        protected array $english
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getGerman(): array
    {
        return $this->german;
    }

    /**
     * @return array<string, string|null>
     */
    public function getEnglish(): array
    {
        return $this->english;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return array_keys($this->english);
    }
}
