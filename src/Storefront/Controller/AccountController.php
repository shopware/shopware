<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * @Route(service="Shopware\Storefront\Controller\AccountController")
 */
class AccountController extends StorefrontController
{
    /**
     * @var AuthenticationUtils
     */
    private $authUtils;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        AuthenticationUtils $authUtils,
        TokenStorageInterface $tokenStorage,
        StorefrontContextPersister $contextPersister
    ){
        $this->authUtils = $authUtils;
        $this->tokenStorage = $tokenStorage;
        $this->contextPersister = $contextPersister;
    }

    /**
     * @Route("/account", name="account_home")
     */
    public function indexAction()
    {
        return $this->renderStorefront('frontend/account/index.html.twig');
    }

    /**
     * @Route("/account/login", name="account_login", methods={"GET"})
     */
    public function loginAction()
    {
        // get the login error if there is one
        $error = $this->authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastEmail = $this->authUtils->getLastUsername();

        return $this->renderStorefront('frontend/register/index.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
        ]);
    }

    /**
     * @Route("/account/login", name="account_login_check", methods={"POST"})
     */
    public function checkLoginAction()
    {
    }

    /**
     * @Route("/account/logout", name="account_logout")
     */
    public function logoutAction(StorefrontContext $context)
    {
        $this->contextPersister->save(
            $context->getToken(),
            [StorefrontContextService::CUSTOMER_ID => null]
        );

        return new Response('<html><body>Admin page!</body></html>');
    }
}
