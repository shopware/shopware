<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\LoginConfig\ConfigBuilder\LoginConfigService;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly ?LoginConfigService $configFactory
    ) {}

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
            throw ApiException::onRateLimitExceeded($exception);
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
        if (!$this->configFactory instanceof LoginConfigService) {
            return new JsonResponse(['useDefault' => true, 'ssoProviders' => [], 'error' => 'LoginConfigService not available']);
        }

        return new JsonResponse($this->configFactory->templateDataToArray($this->configFactory->createTemplateData()));
    }

    #[Route(path: '/api/oauth/sso/code', name: 'api.oauth.sso.code', defaults: ['auth_required' => false], methods: ['GET'])]
    public function code(Request $request, Context $context): Response
    {
        $storedState = $request->getSession()->get('SSO_swsso');
        $state = $request->get('rdm');

        if ($storedState !== $state) {
            throw new \Exception('Invalid state');
        }

        $code = $request->get('code');

        $config = $this->configFactory->getLoginConfigItemByKey('swsso');

        $client = new \GuzzleHttp\Client(['base_uri' => $config->baseUrl]);
        $tokenResponse = $client->post('/oauth/access_token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'scope' => 'openid',
                'client_id' => $config->clientId,
                'client_secret' => $config->clientSecret,
                'code' => $code,
                'redirect_uri' => $config->redirectUri,
            ],
        ]);

        $json = json_decode($tokenResponse->getBody()->getContents(), true);


        $JWT = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $json['id_token'])[1]))));

        $email = $JWT->email;

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $searchResult = $this->userRepository->search($criteria, $context);

        // TODO: REMOVE AFTER DEBUG
        echo '<pre>';
        var_export($searchResult->getEntities()->first());
        echo '<br/>';
        die();
        // TODO: REMOVE AFTER DEBUG

//        $userInfoResponse = $client->get('/openid/userinfo', [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $json['access_token']
//            ],
//        ]);
//
//        $userInfo = json_decode($userInfoResponse->getBody()->getContents(), true);
//
//        // get jwks token
//        $jwksResponse = $client->get('openid/jwks', [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $json['access_token']
//            ],
//        ]);
//
//        $jwks = json_decode($jwksResponse->getBody()->getContents(), true);
//        // TODO: REMOVE AFTER DEBUG
//        echo'<pre>';
//        var_export('$userInfoResponse');
//        var_export($userInfoResponse->getBody()->getContents());
//        var_export('$jwksResponse');
//        var_export($jwksResponse->getBody()->getContents());
//        echo '<br/>';
//        die();
//        // TODO: REMOVE AFTER DEBUG

        return new Response();
    }
}
