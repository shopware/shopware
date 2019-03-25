<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlGenerator;

use Cocur\Slugify\Slugify;
use Shopware\Core\Checkout\Context\CheckoutContextFactoryInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Symfony\Component\Routing\RouterInterface;
use Twig\Loader\ArrayLoader;

class DetailPageSeoUrlGenerator extends SeoUrlGenerator
{
    public const ROUTE_NAME = 'frontend.detail.page';
    public const DEFAULT_TEMPLATE = '{{ product.name |slugify }}/{{ product.id }}';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        CheckoutContextFactoryInterface $checkoutContextFactory,
        Slugify $slugify,
        RouterInterface $router,
        string $routeName,
        EntityRepositoryInterface $productRepository
    ) {
        parent::__construct($salesChannelRepository, $checkoutContextFactory, $slugify, $router, $routeName);

        $this->productRepository = $productRepository;
    }

    public function generateSeoUrls(string $salesChannelId, array $ids, ?string $template = null): iterable
    {
        $this->twig->setLoader(new ArrayLoader(['template' => $template ?? self::DEFAULT_TEMPLATE]));

        $seoUrls = [];
        $products = $this->productRepository->search(new Criteria($ids), $this->getContext($salesChannelId));

        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $seoUrl = new SeoUrlEntity();
            $seoUrl->setSalesChannelId($salesChannelId);
            $seoUrl->setForeignKey($product->getId());

            $pathInfo = $this->router->generate(self::ROUTE_NAME, ['productId' => $product->getId()]);
            $seoUrl->setPathInfo($pathInfo);

            $seoPathInfo = $this->twig->render('template', ['product' => $product]);
            $seoUrl->setSeoPathInfo($seoPathInfo);

            $seoUrl->setIsCanonical(true);
            $seoUrl->setIsModified(false);
            $seoUrl->setIsDeleted(false);
            $seoUrl->setIsValid(true);

            $seoUrls[] = $seoUrl;
        }

        return $seoUrls;
    }

    public function getDefaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }
}
