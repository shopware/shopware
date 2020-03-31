<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoader
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

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        AbstractCategoryRoute $cmsPageRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cmsPageRoute = $cmsPageRoute;
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
            $this->loadMetaData($category, $page);

            $page->setCmsPage($category->getCmsPage());
        }

        $this->eventDispatcher->dispatch(
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadMetaData(CategoryEntity $category, NavigationPage $page): void
    {
        $metaInformation = $page->getMetaInformation();

        $metaDescription = $category->getTranslation('metaDescription')
            ?? $category->getTranslation('description');
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaTitle = $category->getTranslation('metaTitle')
            ?? $category->getTranslation('name');
        $metaInformation->setMetaTitle((string) $metaTitle);

        $metaInformation->setMetaKeywords((string) $category->getTranslation('keywords'));
    }
}
