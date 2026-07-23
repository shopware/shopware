<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRouteResolver $entityRouteResolver)
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
            return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $category->getId());
        }

        $linkType = $category->getTranslation('linkType');
        $internalLink = $category->getTranslation('internalLink');

        if (!$internalLink && $linkType && $linkType !== CategoryDefinition::LINK_TYPE_EXTERNAL) {
            return null;
        }

        switch ($linkType) {
            case CategoryDefinition::LINK_TYPE_PRODUCT:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(ProductDefinition::ENTITY_NAME, $internalLink);

            case CategoryDefinition::LINK_TYPE_CATEGORY:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $internalLink);

            case CategoryDefinition::LINK_TYPE_LANDING_PAGE:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(LandingPageDefinition::ENTITY_NAME, $internalLink);

            case CategoryDefinition::LINK_TYPE_EXTERNAL:
            default:
                return $category->getTranslation('externalLink');
        }
    }
}
