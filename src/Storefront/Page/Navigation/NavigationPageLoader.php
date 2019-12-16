<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoader
{
    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityDefinition
     */
    private $categoryDefinition;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityDefinition $categoryDefinition,
        SalesChannelRepositoryInterface $categoryRepository
    ) {
        $this->cmsPageLoader = $cmsPageLoader;
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->categoryDefinition = $categoryDefinition;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     */
    public function load(Request $request, SalesChannelContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        $navigationId = $request->get('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

        $category = $this->loadCategory($navigationId, $context);

        $pageId = $category->getCmsPageId();

        if ($pageId) {
            $resolverContext = new EntityResolverContext($context, $request, $this->categoryDefinition, $category);

            $pages = $this->cmsPageLoader->load($request, new Criteria([$pageId]), $context, $category->getSlotConfig(), $resolverContext);

            if (!$pages->has($pageId)) {
                throw new PageNotFoundException($pageId);
            }

            $page->setCmsPage($pages->get($pageId));
            $this->loadMetaData($category, $page);
        }

        $this->eventDispatcher->dispatch(
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadMetaData(CategoryEntity $category, NavigationPage $page): void
    {
        $metaInformation = $page->getMetaInformation();

        $metaDescription = $category->getMetaDescription()
            ?? $category->getDescription();
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaTitle = $category->getMetaTitle()
            ?? $category->getTranslation('name');
        $metaInformation->setMetaTitle((string) $metaTitle);

        $metaInformation->setMetaKeywords((string) $category->getKeywords());
    }

    private function loadCategory(string $categoryId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$categoryId]);
        $criteria->addAssociation('media');

        $category = $this->categoryRepository->search($criteria, $context)->get($categoryId);

        if (!$category) {
            throw new CategoryNotFoundException($categoryId);
        }

        return $category;
    }
}
