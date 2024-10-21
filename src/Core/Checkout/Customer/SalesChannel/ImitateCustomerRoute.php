<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api'], '_contextTokenRequired' => false])]
#[Package('checkout')]
class ImitateCustomerRoute extends AbstractImitateCustomerRoute
{
    final public const TOKEN = 'token';
    final public const CUSTOMER_ID = 'customerId';
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

    #[Route(path: '/store-api/account/login/imitate-customer', name: 'store-api.account.imitate-customer-login', methods: ['POST'])]
    public function imitateCustomerLogin(RequestDataBag $requestDataBag, SalesChannelContext $context): ContextTokenResponse
    {
        $this->validateRequestDataFields($requestDataBag, $context->getContext());

        $customerId = $requestDataBag->getString(self::CUSTOMER_ID);

        if ($context->getCustomerId() === $customerId) {
            return new ContextTokenResponse($context->getToken());
        }

        $token = $requestDataBag->getString(self::TOKEN);
        $userId = $requestDataBag->getString(self::USER_ID);

        $this->imitateCustomerTokenGenerator->validate($token, $context->getSalesChannelId(), $customerId, $userId);

        $context->setImitatingUserId($userId);

        if ($context->getCustomer()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->salesChannelContextFactory->create($newTokenResponse->getToken(), $context->getSalesChannelId());
            $context->setImitatingUserId($userId);
        }

        $newToken = $this->accountService->loginById($customerId, $context);

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
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(['entity' => 'customer', 'context' => $context]))
            ->add(self::USER_ID, new Uuid(), new EntityExists(['entity' => 'user', 'context' => $context]));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}
