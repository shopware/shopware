<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Context\StorefrontApiContext;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextValueResolver;
use Shopware\StorefrontApi\Firewall\CustomerUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class CustomerController extends Controller
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    public function __construct(
        Serializer $serializer,
        AuthenticationManagerInterface $authenticationManager,
        TokenStorageInterface $tokenStorage,
        StorefrontContextPersister $contextPersister
    ) {
        $this->serializer = $serializer;
        $this->authenticationManager = $authenticationManager;
        $this->tokenStorage = $tokenStorage;
        $this->contextPersister = $contextPersister;
    }

    /**
     * @Route("/storefront-api/customer/login", name="storefront.api.customer.login")
     * @Method({"POST"})
     */
    public function loginAction(Request $request, StorefrontContext $context): JsonResponse
    {
        $post = $this->getPost($request);

        $username = $post['username'];
        $password = $post['password'];

        $unauthenticatedToken = new UsernamePasswordToken($username, $password, 'storefront');

        $authenticatedToken = $this->authenticationManager->authenticate($unauthenticatedToken);

        $this->tokenStorage->setToken($authenticatedToken);

        /** @var CustomerUser $user */
        $user = $authenticatedToken->getUser();

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null
            ]
        );

        return new JsonResponse([
            StorefrontContextValueResolver::CONTEXT_TOKEN_KEY => $context->getToken()
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout")
     * @Method({"POST"})
     */
    public function logoutAction(StorefrontContext $context): void
    {
        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null
            ]
        );

    }

    private function getPost(Request $request): array
    {
        if (empty($request->getContent())) {
            return [];
        }

        return $this->serializer->decode($request->getContent(), 'json');
    }
}
