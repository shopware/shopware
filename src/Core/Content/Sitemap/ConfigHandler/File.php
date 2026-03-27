<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ConfigHandler;

use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class File implements ConfigHandlerInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private readonly array $excludedUrls;

    /**
     * @var list<array<string, mixed>>
     */
    private readonly array $customUrls;

    /**
     * @internal
     *
     * @param array<string, list<array<string, mixed>>> $sitemapConfig
     */
    public function __construct(array $sitemapConfig)
    {
        $this->customUrls = $sitemapConfig[ConfigHandler::CUSTOM_URLS_KEY];
        $this->excludedUrls = $sitemapConfig[ConfigHandler::EXCLUDED_URLS_KEY];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function getSitemapConfig(): array
    {
        return [
            ConfigHandler::CUSTOM_URLS_KEY => $this->getSitemapCustomUrls($this->customUrls),
            ConfigHandler::EXCLUDED_URLS_KEY => $this->excludedUrls,
        ];
    }

    /**
     * @param list<array<string, mixed>> $customUrls
     *
     * @return list<array<string, mixed>>
     */
    private function getSitemapCustomUrls(array $customUrls): array
    {
        array_walk($customUrls, static function (array &$customUrl): void {
            $customUrl['lastMod'] = \DateTime::createFromFormat('Y-m-d H:i:s', $customUrl['lastMod']);
        });

        return $customUrls;
    }
}
