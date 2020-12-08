<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Content\Sitemap\Struct\UrlResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryUrlProvider implements UrlProviderInterface
{
    public const CHANGE_FREQ = 'daily';

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var ConfigHandler
     */
    private $configHandler;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlPlaceholderHandler;

    public function __construct(
        SalesChannelRepositoryInterface $categoryRepository,
        ConfigHandler $configHandler,
        SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->configHandler = $configHandler;
        $this->seoUrlPlaceholderHandler = $seoUrlPlaceholderHandler;
    }

    public function getName(): string
    {
        return 'category';
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $salesChannelContext, int $limit, ?int $offset = null): UrlResult
    {
        $categories = $this->getCategories($salesChannelContext, $limit, $offset);

        $urls = [];
        $url = new Url();
        foreach ($categories as $category) {
            /** @var \DateTimeInterface $lastmod */
            $lastmod = $category->getUpdatedAt() ?: $category->getCreatedAt();

            $newUrl = clone $url;
            $newUrl->setLoc($this->seoUrlPlaceholderHandler->generate('frontend.navigation.page', ['navigationId' => $category->getId()]));
            $newUrl->setLastmod($lastmod);
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(CategoryEntity::class);
            $newUrl->setIdentifier($category->getId());

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

    private function getCategories(SalesChannelContext $salesChannelContext, int $limit, ?int $offset): CategoryCollection
    {
        $categoriesCriteria = new Criteria();
        $categoriesCriteria->setLimit($limit);
        $categoriesCriteria->addFilter(new EqualsFilter('active', true));
        $categoriesCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('path', '|' . $salesChannelContext->getSalesChannel()->getNavigationCategoryId() . '|'),
            new ContainsFilter('path', '|' . $salesChannelContext->getSalesChannel()->getFooterCategoryId() . '|'),
            new ContainsFilter('path', '|' . $salesChannelContext->getSalesChannel()->getServiceCategoryId() . '|'),
        ]));

        if ($offset !== null) {
            $categoriesCriteria->setOffset($offset);
        }

        $excludedCategoryIds = $this->getExcludedCategoryIds($salesChannelContext);
        if (!empty($excludedCategoryIds)) {
            $categoriesCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $excludedCategoryIds)]));
        }

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search($categoriesCriteria, $salesChannelContext)->getEntities();

        return $categories;
    }

    private function getExcludedCategoryIds(SalesChannelContext $salesChannelContext): array
    {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $excludedUrls = $this->configHandler->get(ConfigHandler::EXCLUDED_URLS_KEY);
        if (empty($excludedUrls)) {
            return [];
        }

        $excludedUrls = array_filter($excludedUrls, static function (array $excludedUrl) use ($salesChannelId) {
            if ($excludedUrl['resource'] !== CategoryEntity::class) {
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
