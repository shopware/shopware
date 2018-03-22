<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextValueResolver;
use Shopware\StorefrontApi\Firewall\ApplicationAuthenticator;
use Shopware\StorefrontApi\Firewall\ContextUser;
use Shopware\StorefrontApi\Firewall\CustomerProvider;
use Shopware\StorefrontApi\Firewall\CustomerUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Serializer\Serializer;

class CustomerController extends Controller
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    /**
     * @var CustomerProvider
     */
    private $customerProvider;

    public function __construct(
        Serializer $serializer,
        StorefrontContextPersister $contextPersister,
        CustomerProvider $customerProvider
    ) {
        $this->serializer = $serializer;
        $this->contextPersister = $contextPersister;
        $this->customerProvider = $customerProvider;
    }

    /**
     * @Route("/storefront-api/customer/login", name="storefront.api.customer.login")
     * @Method({"POST"})
     */
    public function loginAction(Request $request): JsonResponse
    {
        $post = $this->getPost($request);

        if (empty($post['username']) || empty($post['password'])) {
            throw new BadCredentialsException();
        }

        $username = $post['username'];
        $password = $post['password'];

        $user = $this->customerProvider->loadUserByUsername($username);

        /** @var ContextUser $context */
        $context = $this->getUser();

        $this->contextPersister->save(
            $context->getContextToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ]
        );

        return new JsonResponse([
            ApplicationAuthenticator::CONTEXT_TOKEN_KEY => $context->getContextToken(),
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout")
     * @Method({"POST"})
     */
    public function logoutAction(): void
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $this->contextPersister->save(
            $user->getContextToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
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
