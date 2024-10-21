<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class NavigationPageLoader implements NavigationPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCategoryRoute $cmsPageRoute,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer
    ) {
    }

    public function load(Request $request, SalesChannelContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        $navigationId = $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

        $category = $this->cmsPageRoute
            ->load($navigationId, $request, $context)
            ->getCategory();

        if (!$category->getActive()) {
            throw CategoryException::categoryNotFound($category->getId());
        }

        $this->loadMetaData($category, $page, $context->getSalesChannel());
        $page->setNavigationId($category->getId());
        $page->setCategory($category);

        if ($category->getCmsPage()) {
            $page->setCmsPage($category->getCmsPage());
        }

        if ($page->getMetaInformation()) {
            $canonical = ($navigationId === $context->getSalesChannel()->getNavigationCategoryId())
                ? $this->seoUrlReplacer->generate('frontend.home.page')
                : $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $navigationId]);

            $page->getMetaInformation()->setCanonical($canonical);
        }

        $this->eventDispatcher->dispatch(
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadMetaData(CategoryEntity $category, NavigationPage $page, SalesChannelEntity $salesChannel): void
    {
        $metaInformation = $page->getMetaInformation();

        if ($metaInformation === null) {
            return;
        }

        $isHome = $salesChannel->getNavigationCategoryId() === $category->getId();

        $metaDescription = $isHome && $salesChannel->getTranslation('homeMetaDescription')
            ? $salesChannel->getTranslation('homeMetaDescription')
            : $category->getTranslation('metaDescription')
            ?? $category->getTranslation('description');
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaTitle = $isHome && $salesChannel->getTranslation('homeMetaTitle')
            ? $salesChannel->getTranslation('homeMetaTitle')
            : $category->getTranslation('metaTitle')
            ?? $category->getTranslation('name');
        $metaInformation->setMetaTitle((string) $metaTitle);

        $keywords = $isHome && $salesChannel->getTranslation('homeKeywords')
            ? $salesChannel->getTranslation('homeKeywords')
            : $category->getTranslation('keywords');
        $metaInformation->setMetaKeywords((string) $keywords);
    }
}
