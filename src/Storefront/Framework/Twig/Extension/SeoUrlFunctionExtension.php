<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlPlaceholderHandler;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SeoUrlFunctionExtension extends AbstractExtension
{
    /**
     * @var AbstractExtension
     */
    private $routingExtension;

    /**
     * @var SeoUrlPlaceholderHandler
     */
    private $seoUrlReplacer;

    public function __construct(RoutingExtension $extension, SeoUrlPlaceholderHandler $seoUrlReplacer)
    {
        $this->routingExtension = $extension;
        $this->seoUrlReplacer = $seoUrlReplacer;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoUrl', [$this, 'seoUrl'], ['is_safe_callback' => [$this->routingExtension, 'isUrlGenerationSafe']]),
            new TwigFunction('productUrl', [$this, 'productUrl']),
            new TwigFunction('navigationUrl', [$this, 'navigationUrl']),
        ];
    }

    public function seoUrl($name, $parameters = []): string
    {
        return $this->seoUrlReplacer->generate($name, $parameters);
    }

    /**
     * @deprecated Use seoUrl
     */
    public function productUrl(ProductEntity $product): string
    {
        return $this->seoUrl(
            'frontend.detail.page',
            ['productId' => $product->getId()]
        );
    }

    /**
     * @deprecated Use seoUrl
     */
    public function navigationUrl(CategoryEntity $category): string
    {
        return $this->seoUrl(
            'frontend.navigation.page',
            ['navigationId' => $category->getId()]
        );
    }
}
