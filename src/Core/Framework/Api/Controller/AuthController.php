<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Cassandra\Date;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use League\OAuth2\Server\AuthorizationServer;
use pq\DateTime;
use Shopware\Administration\Login\TestGrantType;
use Shopware\Administration\LoginConfig\ConfigBuilder\LoginConfigService;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\OAuth\AccessTokenRepository;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    public function code(Request $request, Context $context): Response
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
        $userData = $this->getUserData($json['id_token'], $context);

        $response = new Response();
        $request->headers->set('grant_type', 'test_grant');
        $request->request->set('grant_type', 'test_grant');
        $request->request->set('user_id', $userData->user->getId());

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse($response);
        $this->authorizationServer->enableGrantType(new TestGrantType(new AccessTokenRepository(), $this->container->get('shopware.jwt_config')));
        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        // TODO: Rate limit the response here

        $resp = (new HttpFoundationFactory())->createResponse($response);

        $data = json_decode($resp->getContent(), true);
        $uri = '/admin';

        $redResp = new RedirectResponse($uri, Response::HTTP_FOUND);

        $dt = strtotime('+' . $data['expires_in'] . ' seconds')  * 1000;

        $redResp->headers->setCookie(new Cookie(
            'bearerAuth',
            \json_encode(
                [
                    'access' => $data['access_token'],
                    'refresh' => $data['refresh_token'],
                    'expiry' => $dt,
                ]
            ),
            (int) $dt,
            '/',
            null,
            false,
            false,
            false
        ));

        return $redResp;
    }

    private function getUserData(string $idToken, Context $context): \stdClass
    {
        $parser = new Parser(new JoseEncoder());
        $jwt = $parser->parse($idToken);
        // TODO: We need to validate the JWT here! For this its required we have the public key of the auth server

        /**
         * Example JWT claims:
         *
         * array: [
         *      "aud" => array: [
         *          0 => "ffffffff-ffff-ffff-ffff-ffffffffffff"
         *      ]
         *      "iss" => string: "https://api.shopware.com"
         *      "iat" => DateTimeImmutable: {
         *          date: 1970-01-01 00:00:00.0 +00:00
         *      }
         *      "exp" => DateTimeImmutable: {
         *          date: 1970-01-01 00:00:00.0 +00:00
         *      }
         *      "sub" => string: "123456"
         *      "email" => string: "test@shopware.com"
         * ]
         */
        $data = $jwt->claims();

        $email = $data->get('email');
        $sub = $data->get('sub');
        $exp = $data->get('exp');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $userSearchResult = $this->userRepository->search($criteria, $context);
        $user = $userSearchResult->first();
        if ($user === null) {
            throw ApiException::userNotFound();
        }

        // TODO: IMPORTANT
        // 1: Add sub property to user entity
        // 2: Search for user by sub property
        // 3: If user not found, search for user by email
        // 4: If user found and sub property is empty, update user entity with sub property else throw exception

        // TODO: create a nice return type for this
        $ret = new \stdClass();
        $ret->email = $email;
        $ret->sub = $sub;
        $ret->exp = $exp;
        $ret->user = $user;

        return $ret;
    }
}
