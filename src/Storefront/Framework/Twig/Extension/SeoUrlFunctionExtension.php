<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SeoUrlFunctionExtension extends AbstractExtension
{
    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('productUrl', [$this, 'productUrl']),
            new TwigFunction('navigationUrl', [$this, 'navigationUrl']),
            new TwigFunction('canonicalUrl', [$this, 'canonicalUrl']),
        ];
    }

    public function productUrl(ProductEntity $product): string
    {
        if (!$product->hasExtension('canonicalUrl')) {
            $productUrl = $this->router->generate(
                'frontend.detail.page',
                ['productId' => $product->getId()],
                RouterInterface::ABSOLUTE_URL
            );

            return $productUrl;
        }

        /** @var SeoUrlEntity $canonical */
        $canonical = $product->getExtension('canonicalUrl');

        return $canonical->getUrl();
    }

    public function navigationUrl(CategoryEntity $category): string
    {
        if (!$category->hasExtension('canonicalUrl')) {
            return $this->router->generate(
                'frontend.navigation.page',
                ['navigationId' => $category->getId()],
                RouterInterface::ABSOLUTE_URL
            );
        }

        /** @var SeoUrlEntity $canonical */
        $canonical = $category->getExtension('canonicalUrl');

        return $canonical->getUrl();
    }

    public function canonicalUrl(Struct $entity, string $fallback): string
    {
        if (!$entity->hasExtension('canonicalUrl')) {
            return $fallback;
        }

        /** @var SeoUrlEntity $canonical */
        $canonical = $entity->getExtension('canonicalUrl');

        return $canonical->getUrl();
    }
}
