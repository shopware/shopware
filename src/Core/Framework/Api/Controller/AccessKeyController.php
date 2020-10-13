<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class AccessKeyController extends AbstractController
{
    /**
     * @Route("/api/v{version}/_action/access-key/intergration", name="api.action.access-key.integration", methods={"GET"})
     * @Acl({"api_action_access-key_integration"})
     */
    public function generateIntegrationKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/access-key/user", name="api.action.access-key.user", methods={"GET"})
     */
    public function generateUserKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('user'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/access-key/sales-channel", name="api.action.access-key.sales-channel", methods={"GET"})
     */
    public function generateSalesChannelKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/access-key/product-export", name="api.action.access-key.product-export", methods={"GET"})
     */
    public function generateProductExportKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('product-export'),
        ]);
    }
}
