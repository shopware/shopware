<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class AuthController extends Controller
{
    /**
     * Dummy route for JWT authentication
     *
     * @Route("/api/auth", name="api_auth")
     */
    public function authAction(): void
    {
        throw new NotAcceptableHttpException();
    }
}
