<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Translations
{
    /**
     * @var array<string, string|null>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $german;

    /**
     * @var array<string, string|null>
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $english;

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
