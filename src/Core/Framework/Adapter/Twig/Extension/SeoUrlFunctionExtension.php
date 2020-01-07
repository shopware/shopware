<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
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
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlReplacer;

    public function __construct(RoutingExtension $extension, SeoUrlPlaceholderHandlerInterface $seoUrlReplacer)
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

    public function seoUrl(string $name, array $parameters = []): string
    {
        return $this->seoUrlReplacer->generate($name, $parameters);
    }

    /**
     * @deprecated tag:v6.1.0 Use seoUrl
     */
    public function productUrl(ProductEntity $product): string
    {
        return $this->seoUrl(
            'frontend.detail.page',
            ['productId' => $product->getId()]
        );
    }

    /**
     * @deprecated tag:v6.1.0 Use seoUrl
     */
    public function navigationUrl(CategoryEntity $category): string
    {
        return $this->seoUrl(
            'frontend.navigation.page',
            ['navigationId' => $category->getId()]
        );
    }
}
