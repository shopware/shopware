<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
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
 * @Acl({"system.plugin_maintain"})
 */
class ExtensionStoreLicensesController extends AbstractController
{
    private AbstractExtensionStoreLicensesService $extensionStoreLicensesService;

    public function __construct(AbstractExtensionStoreLicensesService $extensionStoreLicensesService)
    {
        $this->extensionStoreLicensesService = $extensionStoreLicensesService;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/license/cancel/{licenseId}", name="api.license.cancel", methods={"DELETE"})
     */
    public function cancelSubscription(int $licenseId, Context $context): JsonResponse
    {
        $this->extensionStoreLicensesService->cancelSubscription($licenseId, $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/license/rate/{extensionId}", name="api.license.rate", methods={"POST"})
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
