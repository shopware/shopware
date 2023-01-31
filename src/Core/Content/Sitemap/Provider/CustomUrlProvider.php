<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
class CustomUrlProvider extends AbstractUrlProvider
{
    /**
     * @internal
     */
    public function __construct(private readonly ConfigHandler $configHandler)
    {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'custom';
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $sitemapCustomUrls = $this->configHandler->get(ConfigHandler::CUSTOM_URLS_KEY);

        $urls = [];
        $url = new Url();
        foreach ($sitemapCustomUrls as $sitemapCustomUrl) {
            if (!$this->isAvailableForSalesChannel($sitemapCustomUrl, $context->getSalesChannel()->getId())) {
                continue;
            }

            $newUrl = clone $url;
            $newUrl->setLoc($sitemapCustomUrl['url']);
            $newUrl->setLastmod($sitemapCustomUrl['lastMod']);
            $newUrl->setChangefreq($sitemapCustomUrl['changeFreq']);
            $newUrl->setResource('custom');
            $newUrl->setIdentifier('');

            $urls[] = $newUrl;
        }

        return new UrlResult($urls, null);
    }

    private function isAvailableForSalesChannel(array $url, ?string $salesChannelId): bool
    {
        return \in_array($url['salesChannelId'], [$salesChannelId, null], true);
    }
}
