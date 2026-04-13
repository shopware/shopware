<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer)
    {
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string
    {
        if ($category->getType() === CategoryDefinition::TYPE_FOLDER) {
            return null;
        }

        if ($category->getType() !== CategoryDefinition::TYPE_LINK) {
            /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12970) */
            return $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $category->getId()]);
        }

        $linkType = $category->getTranslation('linkType');
        $internalLink = $category->getTranslation('internalLink');

        if (!$internalLink && $linkType && $linkType !== CategoryDefinition::LINK_TYPE_EXTERNAL) {
            return null;
        }

        switch ($linkType) {
            case CategoryDefinition::LINK_TYPE_PRODUCT:
                /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12970) */
                return $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_CATEGORY:
                if ($salesChannel !== null && $internalLink === $salesChannel->getNavigationCategoryId()) {
                    /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12970) */
                    return $this->seoUrlReplacer->generate('frontend.home.page');
                }

                /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12970) */
                return $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_LANDING_PAGE:
                /** @phpstan-ignore shopware.storefrontRouteUsage (Do not use Storefront routes in the core. Will be fixed with https://github.com/shopware/shopware/issues/12970) */
                return $this->seoUrlReplacer->generate('frontend.landing.page', ['landingPageId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_EXTERNAL:
            default:
                return $category->getTranslation('externalLink');
        }
    }
}
