<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Exception\InvalidExtensionIdException;
use Shopware\Core\Framework\Store\Exception\InvalidVariantIdException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class ExtensionStoreLicensesController extends AbstractController
{
    /**
     * @var AbstractExtensionStoreLicensesService
     */
    private $extensionStoreLicensesService;

    public function __construct(AbstractExtensionStoreLicensesService $extensionStoreLicensesService)
    {
        $this->extensionStoreLicensesService = $extensionStoreLicensesService;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/licensed", name="api.extension.licensed", methods={"GET"})
     */
    public function getLicensedExtensions(Context $context): Response
    {
        $listing = $this->extensionStoreLicensesService->getLicensedExtensions($context);

        return new JsonResponse([
            'data' => $listing,
            'meta' => [
                'total' => $listing->getTotal(),
            ],
        ]);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/_action/extension/purchase", name="api.extension.purchase", methods={"POST"})
     */
    public function purchaseExtension(Request $request, Context $context): JsonResponse
    {
        $extensionId = $request->request->get('extensionId');
        $variantId = $request->request->get('variantId');

        if (!is_numeric($extensionId)) {
            throw new InvalidExtensionIdException();
        }

        if (!is_numeric($variantId)) {
            throw new InvalidVariantIdException();
        }

        $this->extensionStoreLicensesService->purchaseExtension((int) $extensionId, (int) $variantId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/license/cancel/{licenseId}", name="api.license.cancel", methods={"DELETE"})
     */
    public function cancelSubscription(int $licenseId, Context $context): JsonResponse
    {
        return new JsonResponse(
            $this->extensionStoreLicensesService->cancelSubscription($licenseId, $context)
        );
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/v{version}/license/rate/{extensionId}", name="api.license.rate", methods={"POST"})
     */
    public function rateLicensedExtension(int $extensionId, Request $request, Context $context): JsonResponse
    {
        $this->extensionStoreLicensesService->rateLicensedExtension(
            ReviewStruct::fromRequest($extensionId, $request),
            $context
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
