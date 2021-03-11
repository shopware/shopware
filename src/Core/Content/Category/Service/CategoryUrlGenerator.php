<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class CategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlReplacer;

    public function __construct(SeoUrlPlaceholderHandlerInterface $seoUrlReplacer)
    {
        $this->seoUrlReplacer = $seoUrlReplacer;
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(CategoryEntity $category): ?string
    {
        if ($category->getType() === CategoryDefinition::TYPE_FOLDER) {
            return null;
        }

        if (!Feature::isActive('FEATURE_NEXT_13504')) {
            if ($category->getTranslation('externalLink')) {
                return $category->getTranslation('externalLink');
            }

            return $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $category->getId()]);
        }

        if ($category->getType() !== CategoryDefinition::TYPE_LINK) {
            return $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $category->getId()]);
        }

        $linkType = $category->getTranslation('linkType');
        $internalLink = $category->getTranslation('internalLink');

        if (!$internalLink && $linkType && $linkType !== CategoryDefinition::LINK_TYPE_EXTERNAL) {
            return null;
        }

        switch ($linkType) {
            case CategoryDefinition::LINK_TYPE_PRODUCT:
                return $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_CATEGORY:
                return $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_LANDING_PAGE:
                return $this->seoUrlReplacer->generate('frontend.landing.page', ['landingPageId' => $internalLink]);

            case CategoryDefinition::LINK_TYPE_EXTERNAL:
            default:
                return $category->getTranslation('externalLink');
        }
    }
}
