<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID], '_contextTokenRequired' => true])]
#[Package('checkout')]
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
    ) {
    }

    public function getDecorated(): AbstractConvertGuestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/convert-guest', name: 'store-api.account.convert-guest', methods: ['POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
    public function convertGuest(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer, ?DataValidationDefinition $additionalValidationDefinitions = null): SuccessResponse
    {
        if (!$customer->getGuest()) {
            throw CustomerException::registeredCustomerCannotBeConverted($customer->getId());
        }

        $customerData = [
            'id' => $customer->getId(),
            'email' => $customer->getEmail(),
            'guest' => false,
            'password' => $requestDataBag->get('password'),
        ];

        $this->validate(new DataBag($customerData), $context, $additionalValidationDefinitions);

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function validate(DataBag $data, SalesChannelContext $context, ?DataValidationDefinition $additionalValidationDefinitions = null): void
    {
        $definition = new DataValidationDefinition('customer.guest.convert');
        $definition->merge($this->passwordValidationFactory->create($context));

        if ($additionalValidationDefinitions) {
            $definition->merge($additionalValidationDefinitions);
        }

        $options = ['context' => $context->getContext(), 'salesChannelContext' => $context];
        $definition->add('email', new CustomerEmailUnique($options));

        $validationEvent = new BuildValidationEvent($definition, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}
