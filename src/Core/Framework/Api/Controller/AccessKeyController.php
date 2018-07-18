<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class AccessKeyController extends Controller
{
    /**
     * @Route("/api/v{version}/integration/actions/generate-key", name="api.integration.actions.generate.key")
     * @Method({"GET"})
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
     * @Route("/api/v{version}/user/actions/generate-key", name="api.user.actions.generate.key")
     * @Method({"GET"})
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
     * @Route("/api/v{version}/touchpoint/actions/generate-key", name="api.touchpoint.actions.generate.key")
     * @Method({"GET"})
     *
     * @return JsonResponse
     */
    public function generateTouchpointKey(): JsonResponse
    {
        return new JsonResponse([
            'accessKey' => AccessKeyHelper::generateAccessKey('touchpoint'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
    }
}
