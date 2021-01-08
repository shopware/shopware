<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Search\ExtensionCriteria;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class ExtensionStoreDataController extends AbstractController
{
    /**
     * @var AbstractExtensionDataProvider
     */
    private $extensionDataProvider;

    public function __construct(AbstractExtensionDataProvider $extensionListingProvider)
    {
        $this->extensionDataProvider = $extensionListingProvider;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/list", name="api.extension.list", methods={"POST", "GET"})
     */
    public function getExtensionList(Request $request, Context $context): Response
    {
        if ($request->getMethod() === Request::METHOD_POST) {
            $criteria = ExtensionCriteria::fromArray($request->request->all());
        } else {
            $criteria = ExtensionCriteria::fromArray($request->query->all());
        }

        $listing = $this->extensionDataProvider->getListing($criteria, $context);

        return new JsonResponse([
            'data' => $listing,
            'meta' => [
                'total' => $listing->getTotal(),
            ],
        ]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/detail/{id}", name="api.extension.detail", methods={"GET"})
     */
    public function detail(int $id, Context $context): Response
    {
        return new JsonResponse($this->extensionDataProvider->getExtensionDetails($id, $context));
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/{id}/reviews", name="api.extension.reviews", methods={"GET"})
     */
    public function reviews(int $id, Request $request, Context $context): Response
    {
        $criteria = ExtensionCriteria::fromArray($request->query->all());

        return new JsonResponse($this->extensionDataProvider->getReviews($id, $criteria, $context));
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/store-filters", name="api.extension.store_filters", Methods={"GET"})
     */
    public function listingFilters(Context $context): JsonResponse
    {
        return new JsonResponse($this->extensionDataProvider->getListingFilters($context));
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/installed", name="api.extension.installed", methods={"GET"})
     */
    public function getInstalledExtensions(Context $context): Response
    {
        return new JsonResponse(
            $this->extensionDataProvider->getInstalledExtensions($context)
        );
    }
}
