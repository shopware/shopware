<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\PlatformRequest;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Firewall\ContextUser;
use Shopware\StorefrontApi\Firewall\CustomerProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    public function login(Request $request, StorefrontContext $context): JsonResponse
    {
        $post = $this->getPost($request);

        if (empty($post['username']) || empty($post['password'])) {
            throw new BadCredentialsException();
        }

        $username = $post['username'];

        $user = $this->customerProvider->loadUserByUsername($username);

        $this->contextPersister->save(
            $context->getToken(),
            [
                'customerId' => $user->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getTenantId()
        );

        return new JsonResponse([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $context->getToken(),
        ]);
    }

    /**
     * @Route("/storefront-api/customer/logout", name="storefront.api.customer.logout")
     * @Method({"POST"})
     */
    public function logout(): void
    {
        /** @var ContextUser $user */
        $user = $this->getUser();

        $this->contextPersister->save(
            $user->getContextToken(),
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $context->getTenantId()
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
