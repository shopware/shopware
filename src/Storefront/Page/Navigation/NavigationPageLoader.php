<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Decoratable()
 */
class NavigationPageLoader implements NavigationPageLoaderInterface
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractCategoryRoute
     */
    private $cmsPageRoute;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlReplacer;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractCategoryRoute $cmsPageRoute,
        SeoUrlPlaceholderHandlerInterface $seoUrlReplacer
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRoute = $cmsPageRoute;
        $this->seoUrlReplacer = $seoUrlReplacer;
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
            throw new CategoryNotFoundException($category->getId());
        }

        if ($category->getCmsPage()) {
            $this->loadMetaData($category, $page, $context->getSalesChannel());

            $page->setCmsPage($category->getCmsPage());
            $page->setNavigationId($category->getId());
        }

        $this->eventDispatcher->dispatch(
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        if ($page->getMetaInformation()) {
            $canonical = ($navigationId === $context->getSalesChannel()->getNavigationCategoryId())
                ? $this->seoUrlReplacer->generate('frontend.home.page')
                : $this->seoUrlReplacer->generate('frontend.navigation.page', ['navigationId' => $navigationId]);

            $page->getMetaInformation()->assign(['canonical' => $canonical]);
        }

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
