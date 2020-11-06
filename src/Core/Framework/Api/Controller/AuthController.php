<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class AuthController extends AbstractController
{
    /**
     * @var AuthorizationServer
     */
    private $authorizationServer;

    /**
     * @var PsrHttpFactory
     */
    private $psrHttpFactory;

    public function __construct(AuthorizationServer $authorizationServer, PsrHttpFactory $psrHttpFactory)
    {
        $this->authorizationServer = $authorizationServer;
        $this->psrHttpFactory = $psrHttpFactory;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/oauth/authorize", name="api.oauth.authorize", defaults={"auth_required"=false}, methods={"POST"})
     */
    public function authorize(Request $request): void
    {
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/oauth/token", name="api.oauth.token", defaults={"auth_required"=false}, methods={"POST"})
     */
    public function token(Request $request): Response
    {
        $response = new Response();

        $psr7Request = $this->psrHttpFactory->createRequest($request);
        $psr7Response = $this->psrHttpFactory->createResponse($response);

        $response = $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);

        return (new HttpFoundationFactory())->createResponse($response);
    }
}
