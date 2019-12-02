<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

class TranslationService
{
    /**
     * @var string[]
     */
    private $mappings;

    /**
     * @param string[] $mappings
     */
    public function __construct(array $mappings)
    {
        $this->mappings = $mappings;
    }

    public function translate(string $key): string
    {
        return $this->mappings[$key] ?? '(!)' . $key;
    }

    public function t(string $key): string
    {
        return $this->translate($key);
    }
}
