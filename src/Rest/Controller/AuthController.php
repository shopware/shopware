<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Rest\Firewall\JWTAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    /**
     * @var JWTAuthenticator
     */
    private $jwtAuthenticator;

    public function __construct(JWTAuthenticator $jwtAuthenticator)
    {
        $this->jwtAuthenticator = $jwtAuthenticator;
    }

    /**
     * Dummy route for JWT authentication
     *
     * @Route("/api/v1/auth", name="api_auth")
     */
    public function auth(Request $request)
    {
        if ($request->getMethod() !== Request::METHOD_POST) {
            throw new NotAcceptableHttpException('Authentication only supported using the POST method.');
        }

        if (!empty($request->getContent())) {
            $content = json_decode($request->getContent(), true);

            $username = $content['username'] ?? '';
            $password = $content['password'] ?? '';

            $expiry = array_key_exists('expiry', $content) ? (int) $content['expiry'] : 0;
        } else {
            $username = $request->get('username', '');
            $password = $request->get('password', '');

            $expiry = (int) $request->get('expiry');
        }

        if (!$expiry) {
            $expiry = 3600;
        }

        if ($this->jwtAuthenticator->checkPassword($username, $password) === false) {
            throw new UnauthorizedHttpException('json', 'Invalid username and/or password.');
        }

        $token = $this->jwtAuthenticator->createToken([
            'username' => $username,
        ]);

        return new JsonResponse([
            'token' => $token,
            'expiry' => time() + $expiry,
        ]);
    }
}
