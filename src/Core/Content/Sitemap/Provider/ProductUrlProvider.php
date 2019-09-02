<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Provider;

use function array_column;
use function array_filter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ProductUrlProvider implements UrlProviderInterface
{
    public const CHANGE_FREQ = 'hourly';

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

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
        SalesChannelRepositoryInterface $productRepository,
        RouterInterface $router,
        ConfigHandler $configHandler,
        int $batchsize
    ) {
        $this->productRepository = $productRepository;
        $this->router = $router;
        $this->configHandler = $configHandler;
        $this->batchsize = $batchsize;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(SalesChannelContext $salesChannelContext): array
    {
        $products = $this->getProducts($salesChannelContext);

        $urls = [];
        $url = new Url();
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $lastmod = $product->getUpdatedAt() ?: $product->getCreatedAt();

            $newUrl = clone $url;
            $newUrl->setLoc($this->router->generate('frontend.detail.page', ['productId' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
            $newUrl->setLastmod($lastmod);
            $newUrl->setChangefreq(self::CHANGE_FREQ);
            $newUrl->setResource(ProductEntity::class);
            $newUrl->setIdentifier($product->getId());

            $urls[] = $newUrl;
        }

        return $urls;
    }

    public function reset(): void
    {
        $this->counter = 0;
    }

    private function getProducts(SalesChannelContext $salesChannelContext): ProductCollection
    {
        $offset = $this->counter++ * $this->batchsize;

        $productsCriteria = new Criteria();
        $productsCriteria->setLimit($this->batchsize)
            ->setOffset($offset);

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
