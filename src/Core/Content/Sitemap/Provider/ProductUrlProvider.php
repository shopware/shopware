<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductUrlProvider implements UrlProviderInterface
{
    public const CHANGE_FREQ = 'hourly';

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ConfigHandler
     */
    private $configHandler;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        ConfigHandler $configHandler,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler
    ) {
        $this->productRepository = $productRepository;
        $this->configHandler = $configHandler;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
    }

    public function getName(): string
    {
        return 'product';
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $salesChannelContext, int $limit, ?int $offset = null): UrlResult
    {
        $products = $this->getProducts($salesChannelContext, $limit, $offset);

        $urls = [];
        $url = new Url();
        foreach ($products as $product) {
            /** @var \DateTimeInterface $lastmod */
            $lastmod = $product->getUpdatedAt() ?: $product->getCreatedAt();

            $newUrl = clone $url;
            $newUrl->setLoc($this->seoUrlPlaceholderHandler->generate('frontend.detail.page', ['productId' => $product->getId()]));
            $newUrl->setLastmod($lastmod);
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(ProductEntity::class);
            $newUrl->setIdentifier($product->getId());

            $urls[] = $newUrl;
        }

        if (\count($urls) < $limit) { // last run
            $nextOffset = null;
        } elseif ($offset === null) { // first run
            $nextOffset = $limit;
        } else { // 1+n run
            $nextOffset = $offset + $limit;
        }

        return new UrlResult($urls, $nextOffset);
    }

    private function getProducts(SalesChannelContext $salesChannelContext, int $limit, ?int $offset): ProductCollection
    {
        $productsCriteria = new Criteria();
        $productsCriteria->setLimit($limit);

        if ($offset !== null) {
            $productsCriteria->setOffset($offset);
        }

        $excludedProductIds = $this->getExcludedProductIds($salesChannelContext);
        if (!empty($excludedProductIds)) {
            $productsCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $excludedProductIds)]));
        }

        /** @var ProductCollection $products */
        $products = $this->productRepository->search($productsCriteria, $salesChannelContext)->getEntities();

        return $products;
    }

    private function getExcludedProductIds(SalesChannelContext $salesChannelContext): array
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $excludedUrls = $this->configHandler->get(ConfigHandler::EXCLUDED_URLS_KEY);
        if (empty($excludedUrls)) {
            return [];
        }

        $excludedUrls = array_filter($excludedUrls, static function (array $excludedUrl) use ($salesChannelId) {
            if ($excludedUrl['resource'] !== ProductEntity::class) {
                return false;
            }

            if ($excludedUrl['salesChannelId'] !== $salesChannelId) {
                return false;
            }

            return true;
        });

        return array_column($excludedUrls, 'identifier');
    }
}
