<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @deprecated tag:v6.4.0 - Use Store-API instead
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelCmsPageController extends AbstractController
{
    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $cmsPageLoader;

    /**
     * @var CmsPageDefinition
     */
    private $cmsPageDefinition;

    public function __construct(
        SalesChannelCmsPageLoaderInterface $cmsPageLoader,
        CmsPageDefinition $cmsPageDefinition
    ) {
        $this->cmsPageLoader = $cmsPageLoader;
        $this->cmsPageDefinition = $cmsPageDefinition;
    }

    /**
     * @Route("/sales-channel-api/v{version}/cms-page/{pageId}", name="sales-channel-api.cms.page", methods={"GET"})
     */
    public function getPage(string $pageId, Request $request, SalesChannelContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $pages = $this->cmsPageLoader->load($request, new Criteria([$pageId]), $context);

        if (!$pages->has($pageId)) {
            throw new PageNotFoundException($pageId);
        }

        return $responseFactory->createDetailResponse(
            new Criteria(),
            $pages->get($pageId),
            $this->cmsPageDefinition,
            $request,
            $context->getContext()
        );
    }
}
