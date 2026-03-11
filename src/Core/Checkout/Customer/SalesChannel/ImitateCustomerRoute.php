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
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => false,
    ]
)]
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
        private readonly DataValidator $dataValidator,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function getDecorated(): AbstractImitateCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/login/imitate-customer',
        name: 'store-api.account.imitate-customer-login',
        methods: [Request::METHOD_POST]
    )]
    public function imitateCustomerLogin(Request $request, SalesChannelContext $context): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        if (!Feature::isActive('v6.8.0.0')) {
            $legacyPayload = $this->mapLegacyPayload($payload);
            $tokenString = $legacyPayload->token;

            $requestDataBag = new RequestDataBag([
                self::TOKEN => $legacyPayload->token,
                self::CUSTOMER_ID => $legacyPayload->customerId,
                self::USER_ID => $legacyPayload->userId,
            ]);
            $this->validateRequestDataFields($requestDataBag, $context->getContext());

            $token = new ImitateCustomerToken();
            $token->customerId = $legacyPayload->customerId;
            $token->iss = $legacyPayload->userId;

            Feature::silent('v6.8.0.0', fn () => $this->imitateCustomerTokenGenerator->validate($tokenString, $context->getSalesChannelId(), $token->customerId, $token->iss));
        } else {
            $jwtPayload = $this->mapJwtPayload($payload);
            $tokenString = $jwtPayload->token;

            $token = $this->imitateCustomerTokenGenerator->decode($tokenString);

            if ($token->salesChannelId !== $context->getSalesChannelId()) {
                throw CustomerException::invalidImitationToken($tokenString);
            }
        }

        if ($context->getCustomerId() === $token->customerId) {
            $response = new JsonResponse(new ImitateCustomerLoginResponseDTO());
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

            return $response;
        }

        if ($context->getCustomer()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->salesChannelContextFactory->create($newTokenResponse->getToken(), $context->getSalesChannelId());
        }

        $context->setImitatingUserId($token->iss);

        $newToken = $this->accountService->loginById($token->customerId, $context);

        $response = new JsonResponse(new ImitateCustomerLoginResponseDTO());
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        return $response;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateRequestDataFields(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('impersonation.login');

        $definition
            ->add(self::CUSTOMER_ID, new EntityExists(entity: 'customer', context: $context))
            ->add(self::USER_ID, new EntityExists(entity: 'user', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->dataValidator->validate($data->all(), $definition);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapLegacyPayload(array $payload): LegacyImpersonationPayloadDTO
    {
        $dto = new LegacyImpersonationPayloadDTO(
            token: $this->payloadString($payload, self::TOKEN),
            customerId: $this->payloadString($payload, self::CUSTOMER_ID),
            userId: $this->payloadString($payload, self::USER_ID),
        );

        $violations = $this->validator->validate($dto);
        $this->throwIfViolationsExist($violations, $dto);

        return $dto;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapJwtPayload(array $payload): JwtImpersonationPayloadDTO
    {
        $dto = new JwtImpersonationPayloadDTO(
            token: $this->payloadString($payload, self::TOKEN),
        );

        $violations = $this->validator->validate($dto);
        $this->throwIfViolationsExist($violations, $dto);

        return $dto;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param mixed $value
     */
    private function throwIfViolationsExist(ConstraintViolationListInterface $violations, mixed $value): void
    {
        if ($violations->count() === 0) {
            return;
        }

        throw new UnprocessableEntityHttpException(previous: new ValidationFailedException($value, $violations));
    }

}
