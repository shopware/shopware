<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends Controller
{
    /**
     * @var AuthorizationServer
     */
    private $authorizationServer;

    public function __construct(AuthorizationServer $authorizationServer)
    {
        $this->authorizationServer = $authorizationServer;
    }

    /**
     * @Route("/api/oauth/authorize", name="api.oauth.authorize")
     */
    public function authorize(Request $request)
    {
    }

    /**
     * @Route("/api/oauth/token", name="api.oauth.token")
     */
    public function token(Request $request)
    {
        $response = new Response();

        $psr7Factory = new DiactorosFactory();
        $psr7Request = $psr7Factory->createRequest($request);
        $psr7Response = $psr7Factory->createResponse($response);

        return $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
    }
}
