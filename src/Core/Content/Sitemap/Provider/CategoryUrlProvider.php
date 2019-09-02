<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class CategoryUrlProvider implements UrlProviderInterface
{
    public const CHANGE_FREQ = 'daily';

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var int
     */
    private $batchsize;

    /**
     * @var ConfigHandler
     */
    private $configHandler;

    /**
     * @var int
     */
    private $counter = 0;

    public function __construct(
        SalesChannelRepositoryInterface $categoryRepository,
        RouterInterface $router,
        ConfigHandler $configHandler,
        int $batchsize
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->router = $router;
        $this->configHandler = $configHandler;
        $this->batchsize = $batchsize;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $salesChannelContext): array
    {
        $categories = $this->getCategories($salesChannelContext);

        $urls = [];
        $url = new Url();
        /** @var CategoryEntity $category */
        foreach ($categories as $category) {
            $lastmod = $category->getUpdatedAt() ?: $category->getCreatedAt();

            $newUrl = clone $url;
            $newUrl->setLoc($this->router->generate('frontend.navigation.page', ['navigationId' => $category->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
            $newUrl->setLastmod($lastmod);
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(CategoryEntity::class);
            $newUrl->setIdentifier($category->getId());

            $urls[] = $newUrl;
        }

        return $urls;
    }

    public function reset(): void
    {
        $this->counter = 0;
    }

    private function getCategories(SalesChannelContext $salesChannelContext): CategoryCollection
    {
        $offset = $this->counter++ * $this->batchsize;

        $categoriesCriteria = new Criteria();
        $categoriesCriteria->setLimit($this->batchsize)
            ->setOffset($offset);

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
