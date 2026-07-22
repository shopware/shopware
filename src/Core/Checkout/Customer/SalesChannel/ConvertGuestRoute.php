<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ConvertGuestRoute extends AbstractConvertGuestRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractConvertGuestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/convert-guest',
        name: 'store-api.account.convert-guest',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function convertGuest(
        RequestDataBag $requestDataBag,
        SalesChannelContext $context,
        CustomerEntity $customer,
        ?DataValidationDefinition $additionalValidationDefinitions = null
    ): SuccessResponse {
        if (!$customer->getGuest()) {
            throw CustomerException::registeredCustomerCannotBeConverted($customer->getId());
        }

        if ($this->requestStack->getMainRequest() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $customer->getId());
        }

        $requestDataBag->add([
            'email' => $customer->getEmail(),
        ]);
        $this->validate($requestDataBag, $context, $additionalValidationDefinitions);

        $this->customerRepository->update([
            [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'guest' => false,
                'password' => $requestDataBag->get('password'),
            ],
        ], $context->getContext());

        return new SuccessResponse();
    }

    private function validate(RequestDataBag $data, SalesChannelContext $context, ?DataValidationDefinition $additionalValidationDefinitions = null): void
    {
        $definition = new DataValidationDefinition('customer.guest.convert');
        $definition->merge($this->passwordValidationFactory->create($context));

        if ($additionalValidationDefinitions) {
            $definition->merge($additionalValidationDefinitions);
        }

        $definition->add('email', new CustomerEmailUnique(salesChannelContext: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}
