<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\StorefrontApi\Context\StorefrontApiContext;
use Shopware\StorefrontApi\Context\StorefrontApiContextPersister;
use Shopware\StorefrontApi\Context\StorefrontApiContextValueResolver;
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
     * @var \Shopware\StorefrontApi\Context\StorefrontApiContextPersister
     */
    private $contextPersister;

    public function __construct(
        Serializer $serializer,
        AuthenticationManagerInterface $authenticationManager,
        TokenStorageInterface $tokenStorage,
        StorefrontApiContextPersister $contextPersister
    ) {
        $this->serializer = $serializer;
        $this->authenticationManager = $authenticationManager;
        $this->tokenStorage = $tokenStorage;
        $this->contextPersister = $contextPersister;
    }

    /**
     * @Route("/storefront-api/customer/login", name="storefront.api.customer.login")
     * @Method({"POST"})
     *
     * @param \Shopware\StorefrontApi\Context\StorefrontApiContext $context
     * @return JsonResponse
     */
    public function loginAction(Request $request, StorefrontApiContext $context)
    {
        $post = $this->getPost($request);

        $username = $post['username'];
        $password = $post['password'];

        $unauthenticatedToken = new UsernamePasswordToken($username, $password, 'storefront');

        $authenticatedToken = $this->authenticationManager->authenticate($unauthenticatedToken);

        $this->tokenStorage->setToken($authenticatedToken);

        /** @var \Shopware\StorefrontApi\Firewall\CustomerUser $user */
        $user = $authenticatedToken->getUser();

        $this->contextPersister->save(
            $context->getContextToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null
            ]
        );

        return new JsonResponse([
            StorefrontApiContextValueResolver::CONTEXT_TOKEN_KEY => $context->getContextToken(),
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout")
     * @Method({"POST"})
     *
     * @param \Shopware\StorefrontApi\Context\StorefrontApiContext $context
     * @return void
     */
    public function logoutAction(StorefrontApiContext $context)
    {
        $this->contextPersister->save(
            $context->getContextToken(),
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
