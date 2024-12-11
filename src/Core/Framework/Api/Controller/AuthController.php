<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Administration\LoginConfig\ConfigBuilder\LoginConfigService;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\LoginConfig\Builder\LoginConfigBuilder;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('services-settings')]
class AuthController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly RateLimiter $rateLimiter,
        private readonly EntityRepository $userRepository,
        private readonly ?LoginConfigService $loginConfigService,
        private readonly HttpClientInterface $client,
        private readonly LoginConfigBuilder $loginConfigBuilder,
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Remove endpoint "/api/oauth/authorize"
     */
    #[Route(path: '/api/oauth/authorize', name: 'api.oauth.authorize', defaults: ['auth_required' => false], methods: ['POST'])]
    public function authorize(Request $request): void
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0'));
    }

    #[Route(path: '/api/oauth/token', name: 'api.oauth.token', defaults: ['auth_required' => false], methods: ['POST'])]
    public function token(Request $request): Response
    {
        $response = new Response();

        try {
            $cacheKey = $request->get('username') . '-' . $request->getClientIp();

            $this->rateLimiter->ensureAccepted(RateLimiter::OAUTH, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw ApiException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse($response);

        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        $this->rateLimiter->reset(RateLimiter::OAUTH, $cacheKey);

        return (new HttpFoundationFactory())->createResponse($response);
    }

    #[Route(path: '/api/oauth/sso/config', name: 'api.oauth.sso.config', defaults: ['auth_required' => false], methods: ['GET'])]
    public function loginButtonConfig(Request $request): JsonResponse
    {
        if (!$this->loginConfigService instanceof LoginConfigService) {
            return new JsonResponse(['useDefault' => true, 'ssoProviders' => [], 'error' => 'LoginConfigService not available']);
        }

        $templateData = $this->loginConfigService->createTemplateData();
        foreach ($templateData['ssoProviders'] as $key => $provider) {
            $request->getSession()->set('SSO_' . $key, $provider->random);
        }

        return new JsonResponse($templateData);
    }

    #[Route(path: '/api/oauth/sso/code', name: 'api.oauth.sso.code', defaults: ['auth_required' => false], methods: ['GET'])]
    public function code(Request $request): Response
    {
        $storedState = $request->getSession()->get('SSO_swsso');
        $state = $request->get('rdm');

        if ($storedState !== $state) {
            throw ApiException::invalidLoginState();
        }

        $code = $request->get('code');

        if (!$this->loginConfigService instanceof LoginConfigService) {
            throw ApiException::loginConfigServiceNotAvailable();
        }

        $config = $this->loginConfigService->getLoginConfigItemByKey('swsso');

        $tokenResponse = $this->client->request('POST', $config->baseUrl . '/oauth/access_token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'scope' => 'openid',
                'client_id' => $config->clientId,
                'client_secret' => $config->clientSecret,
                'code' => $code,
                'redirect_uri' => $config->redirectUri,
            ],
        ]);

        $json = json_decode($tokenResponse->getContent(), true);

        // the following code is just an example and should be replaced with real user handling
        $this->userRepository->search(new Criteria(), Context::createDefaultContext());

        return new Response($json);
    }
}
