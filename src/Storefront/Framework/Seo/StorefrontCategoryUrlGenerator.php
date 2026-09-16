<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontCategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    private const HOME_PAGE_ROUTE = 'frontend.home.page';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCategoryUrlGenerator $decorated,
        private readonly RouterInterface $router,
    ) {
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        return $this->decorated;
    }

    public function generate(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string
    {
        if ($salesChannel !== null && $this->isHomePageLink($category, $salesChannel)) {
            return $this->router->generate(self::HOME_PAGE_ROUTE);
        }

        return $this->getDecorated()->generate($category, $salesChannel);
    }

    private function isHomePageLink(CategoryEntity $category, SalesChannelEntity $salesChannel): bool
    {
        if (
            $category->getType() !== CategoryDefinition::TYPE_LINK
            || $category->getTranslation('linkType') !== CategoryDefinition::LINK_TYPE_CATEGORY
        ) {
            return false;
        }

        return $category->getTranslation('internalLink') === $salesChannel->getNavigationCategoryId();
    }
}
