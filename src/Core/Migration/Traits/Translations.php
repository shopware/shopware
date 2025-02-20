<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class Translations
{
    /**
     * @var array<string, string|null>
     */
    protected array $german;

    /**
     * @var array<string, string|null>
     */
    protected array $english;

    /**
     * @param array<string, string|null> $german
     * @param array<string, string|null> $english
     */
    public function __construct(
        array $german,
        array $english
    ) {
        $this->german = $german;
        $this->english = $english;
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
