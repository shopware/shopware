<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
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
     * @Route("/api/oauth/authorize", name="api.oauth.authorize", methods={"POST"})
     */
    public function authorize(Request $request): void
    {
    }

    /**
     * @Route("/api/oauth/token", name="api.oauth.token", methods={"POST"})
     */
    public function token(Request $request): ResponseInterface
    {
        $response = new Response();

        $psr7Factory = new DiactorosFactory();
        $psr7Request = $psr7Factory->createRequest($request);
        $psr7Response = $psr7Factory->createResponse($response);

        return $this->authorizationServer->respondToAccessTokenRequest($psr7Request, $psr7Response);
    }
}
