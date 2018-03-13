<?php declare(strict_types=1);

namespace Shopware\Rest\Controller\Storefront;

use Shopware\Rest\Context\ApiStorefrontContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class CustomerController extends Controller
{
    public function loginAction(Request $request, ApiStorefrontContext $context)
    {
        $username = $request->get('username');
        $password = $request->get('password');

        $unauthenticatedToken = new UsernamePasswordToken(
            $username,
            $password,
            $this->providerKey
        );
        $authenticatedToken = $this
            ->authenticationManager
            ->authenticate($unauthenticatedToken);

        $this->tokenStorage->setToken($authenticatedToken);
    }
}
