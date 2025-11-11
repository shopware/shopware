<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID], '_contextTokenRequired' => false])]
#[Package('checkout')]
class ImitateCustomerRoute extends AbstractImitateCustomerRoute
{
    final public const TOKEN = 'token';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const CUSTOMER_ID = 'customerId';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const USER_ID = 'userId';

    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator
    ) {
    }

    public function getDecorated(): AbstractImitateCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-name-change - The parameter `$requestDataBag` will be renamed to `$data` to align with abstract route
     */
    #[Route(path: '/store-api/account/login/imitate-customer', name: 'store-api.account.imitate-customer-login', methods: ['POST'])]
    public function imitateCustomerLogin(RequestDataBag $requestDataBag, SalesChannelContext $context): ContextTokenResponse
    {
        $tokenString = $requestDataBag->getString(self::TOKEN);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->validateRequestDataFields($requestDataBag, $context->getContext());

            $token = new ImitateCustomerToken();
            $token->customerId = $requestDataBag->getString(self::CUSTOMER_ID);
            $token->iss = $requestDataBag->getString(self::USER_ID);

            Feature::silent('v6.8.0.0', fn () => $this->imitateCustomerTokenGenerator->validate($tokenString, $context->getSalesChannelId(), $token->customerId, $token->iss));
        } else {
            $token = $this->imitateCustomerTokenGenerator->decode($tokenString);

            if ($token->salesChannelId !== $context->getSalesChannelId()) {
                throw CustomerException::invalidImitationToken($tokenString);
            }
        }

        if ($context->getCustomerId() === $token->customerId) {
            return new ContextTokenResponse($context->getToken());
        }

        if ($context->getCustomer()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->salesChannelContextFactory->create($newTokenResponse->getToken(), $context->getSalesChannelId());
        }

        $context->setImitatingUserId($token->iss);

        $newToken = $this->accountService->loginById($token->customerId, $context);

        return new ContextTokenResponse($newToken);
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateRequestDataFields(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('impersonation.login');

        $definition
            ->add(self::TOKEN, new NotBlank())
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(entity: 'customer', context: $context))
            ->add(self::USER_ID, new Uuid(), new EntityExists(entity: 'user', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}
