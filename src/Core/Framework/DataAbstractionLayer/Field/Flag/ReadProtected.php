<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;

class ReadProtected extends Flag
{
    private const BASE_URLS = [
        AdminApiSource::class => '/api/v',
        SalesChannelApiSource::class => '/sales-channel-api/v',
    ];

    /**
     * @var array[string]string
     */
    private $protectedSources = [];

    public function __construct(string ...$protectedSources)
    {
        foreach ($protectedSources as $source) {
            $this->protectedSources[$source] = self::BASE_URLS[$source];
        }
    }

    public function getProtectedSources(): array
    {
        return array_keys($this->protectedSources);
    }

    public function isBaseUrlAllowed(string $baseUrl): bool
    {
        foreach ($this->protectedSources as $url) {
            if (mb_strpos($baseUrl, $url) !== false) {
                return false;
            }
        }

        return true;
    }

    public function isSourceAllowed(string $source): bool
    {
        return !isset($this->protectedSources[$source]);
    }

    public function parse(): \Generator
    {
        yield 'read_protected' => [
            array_keys($this->protectedSources),
        ];
    }
}
