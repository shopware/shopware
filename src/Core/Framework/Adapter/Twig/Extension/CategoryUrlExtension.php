<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

#[Package('framework')]
class CategoryUrlExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RoutingExtension $routingExtension,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_url', $this->getCategoryUrl(...), ['needs_context' => true, 'is_safe_callback' => $this->routingExtension->isUrlGenerationSafe(...)]),
            new TwigFunction('category_linknewtab', $this->isLinkNewTab(...)),
        ];
    }

    /**
     * @param array<string, mixed> $twigContext
     *
     * @deprecated tag:v6.9.0 - Use SalesChannelCategoryEntity::getSeoUrl() instead.
     */
    public function getCategoryUrl(array $twigContext, CategoryEntity $category): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            'The "category_url" function is deprecated and will be removed in v6.9.0.0. Use SalesChannelCategoryEntity::getSeoUrl() instead.',
            silentUntil: 'v6.8.0.0',
        );

        if ($category instanceof SalesChannelCategoryEntity) {
            return $category->getSeoUrl();
        }

        $salesChannel = TwigContextHelper::getSalesChannelContext($twigContext)?->getSalesChannel();

        return $this->categoryUrlGenerator->generate($category, $salesChannel);
    }

    /**
     * @deprecated tag:v6.9.0 - Use CategoryEntity::shouldOpenInNewTab() instead.
     */
    public function isLinkNewTab(CategoryEntity $categoryEntity): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            'The "category_linknewtab" function is deprecated and will be removed in v6.9.0.0. Use CategoryEntity::shouldOpenInNewTab() instead.',
            silentUntil: 'v6.8.0.0',
        );

        return $categoryEntity->shouldOpenInNewTab();
    }
}
