<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\LoginResponseService;
use Shopware\Administration\Login\StateValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('after-sales')]
class AdminAuthController extends AbstractController
{
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly LoginConfigService $loginConfigService,
        private readonly LoginResponseService $loginResponseService,
        private readonly StateValidator $stateValidator,
    ) {
    }

    #[Route(path: '/api/oauth/sso/config', name: 'api.oauth.sso.config', defaults: ['auth_required' => false], methods: ['GET'])]
    public function loginButtonConfig(Request $request): JsonResponse
    {
        $loginConfig = $this->loginConfigService->getConfig();
        $random = $this->stateValidator->createRandom($request);
        $templateData = $this->loginConfigService->createTemplateData($random, $loginConfig);

        return new JsonResponse($templateData);
    }

    #[Route(path: '/api/oauth/sso/code', name: 'api.oauth.sso.code', defaults: ['auth_required' => false], methods: ['GET'])]
    public function code(Request $request): Response
    {
        // TODO: NEXT-40530 Ensure request comes only from Alfred!?
        // TODO: NEXT-40530 Ensure Alfred accepts only requests from Ory
        $this->stateValidator->validateRequest($request);

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse(new Response());

        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        return $this->loginResponseService->create($response);
    }
}
