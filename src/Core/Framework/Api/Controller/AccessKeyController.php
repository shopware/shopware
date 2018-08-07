<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AccessKeyController extends Controller
{
    /**
     * @Route("/api/v{version}/integration/actions/generate-key", name="api.integration.actions.generate.key", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function generateIntegrationKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    /**
     * @Route("/api/v{version}/user/actions/generate-key", name="api.user.actions.generate.key", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function generateUserKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('user'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }

    /**
     * @Route("/api/v{version}/sales-channel/actions/generate-key", name="api.sales_channel.actions.generate.key", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function generateSalesChannelKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }
}
