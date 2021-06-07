<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use League\OAuth2\Server\AuthorizationServer;
use OpenApi\Annotations as OA;
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
     * @OA\Post(
     *     path="/oauth/token",
     *     summary="Fetch an access token",
     *     description="Fetch a access token that can be used to perform authenticated requests",
     *     operationId="token",
     *     tags={"Admin API", "Authorization & Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                  "grand_type"
     *             },
     *             description="For more information take a look at the [Authentication documentation](https://shopware.stoplight.io/docs/admin-api/docs/concepts/authentication-authorisation.md).",
     *             @OA\Property(
     *                 property="grand_type",
     *                 description="The grant type that should be used. See [OAuth 2.0 grant](https://oauth2.thephpleague.com/authorization-server/which-grant/) for more information.",
     *                 type="string",
     *                 enum={"password", "refresh_token", "client_credentials"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Authorized successfully.",
     *         @OA\JsonContent(
     *               @OA\Property(
     *                  property="token_type",
     *                  description="Type of the token.",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="expires_in",
     *                  description="Token lifetime in seconds.",
     *                  type="integer"
     *              ),
     *              @OA\Property(
     *                  property="access_token",
     *                  description="The access token that can be used for subsequent requests",
     *                  type="string"
     *              )
     *         )
     *     )
     * )
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
