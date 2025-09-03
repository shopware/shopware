<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class ApiAware extends Flag
{
    private const BASE_URLS = [
        AdminApiSource::class => '/api/',
        SalesChannelApiSource::class => '/store-api/',
    ];

    /**
     * @var array<string, string>
     */
    private array $whitelist = [];

    public function __construct(string ...$protectedSources)
    {
        foreach ($protectedSources as $source) {
            $this->whitelist[$source] = self::BASE_URLS[$source];
        }

        if (empty($protectedSources)) {
            $this->whitelist = self::BASE_URLS;
        }
    }

    public function isBaseUrlAllowed(string $baseUrl): bool
    {
        $baseUrl = rtrim($baseUrl, '/') . '/';

        foreach ($this->whitelist as $url) {
            if (mb_strpos($baseUrl, $url) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isSourceAllowed(string $source): bool
    {
        if ($source === SystemSource::class) {
            return true;
        }

        if (isset($this->whitelist[$source])) {
            return true;
        }

        $parentSources = class_parents($source);

        if (!$parentSources) {
            return false;
        }

        foreach ($parentSources as $parentSource) {
            if (isset($this->whitelist[$parentSource])) {
                return true;
            }
        }

        return false;
    }

    public function parse(): \Generator
    {
        yield 'read_protected' => [
            array_keys($this->whitelist),
        ];
    }
}
