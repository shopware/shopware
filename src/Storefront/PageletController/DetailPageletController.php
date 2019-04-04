<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Detail\Images\DetailImagesPageletLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DetailPageletController extends StorefrontController
{
    /**
     * @var DetailImagesPageletLoader|PageLoaderInterface
     */
    private $imagesLoader;

    public function __construct(PageLoaderInterface $imagesLoader)
    {
        $this->imagesLoader = $imagesLoader;
    }

    /**
     * @Route("/widgets/detail/images", name="widgets.detail.images", methods={"GET"})
     *
     * @throws CartTokenNotFoundException
     */
    public function imagesAction(InternalRequest $request, SalesChannelContext $context): Response
    {
        $page = $this->imagesLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/page/product-detail/zoom-modal-widget.html.twig', ['page' => $page]);
    }
}
