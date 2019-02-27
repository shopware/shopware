<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Storefront;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolver;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CmsPageController extends AbstractController
{
    /**
     * @var StorefrontCmsPageRepository
     */
    private $cmsPageRepository;

    /**
     * @var SlotDataResolver
     */
    private $slotDataResolver;

    public function __construct(StorefrontCmsPageRepository $cmsPageRepository, SlotDataResolver $slotDataResolver)
    {
        $this->cmsPageRepository = $cmsPageRepository;
        $this->slotDataResolver = $slotDataResolver;
    }

    /**
     * @Route("/storefront-api/v1/cms-page/{pageId}", methods={"GET"})
     */
    public function getPage(string $pageId, Request $request, InternalRequest $internalRequest, CheckoutContext $context, ResponseFactoryInterface $responseFactory): Response
    {
        $cmsPage = $this->getCmsPage($pageId, $context);
        $this->loadSlotData($cmsPage, $internalRequest, $context);

        return $responseFactory->createDetailResponse(
            $cmsPage,
            CmsPageDefinition::class,
            $request,
            $context->getContext()
        );
    }

    private function loadSlotData(CmsPageEntity $page, InternalRequest $request, CheckoutContext $context): void
    {
        if (!$page->getBlocks()) {
            return;
        }

        $slots = $this->slotDataResolver->resolve(
            $page->getBlocks()->getSlots(),
            $request,
            $context
        );

        $page->getBlocks()->setSlots($slots);
    }

    private function getCmsPage(string $pageId, CheckoutContext $context): CmsPageEntity
    {
        $pages = $this->cmsPageRepository->read([$pageId], $context);

        if ($pages->count() === 0) {
            throw new PageNotFoundException($pageId);
        }

        return $pages->first();
    }
}
