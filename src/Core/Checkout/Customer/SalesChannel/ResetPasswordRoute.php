<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class ResetPasswordRoute extends AbstractResetPasswordRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $customerRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/recovery-password-confirm', name: 'store-api.account.recovery.password', methods: ['POST'])]
    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): SuccessResponse
    {
        $this->validateResetPassword($data, $context);

        $hash = $data->get('hash');

        if (!$this->checkHash($hash, $context->getContext())) {
            throw CustomerException::customerRecoveryHashExpired($hash);
        }

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));
        $customerHashCriteria->addAssociation('customer');

        $customerRecovery = $this->customerRecoveryRepository->search($customerHashCriteria, $context->getContext())->first();

        if (!$customerRecovery instanceof CustomerRecoveryEntity) {
            throw CustomerException::customerNotFoundByHash($hash);
        }

        $customer = $customerRecovery->getCustomer();

        if (!$customer) {
            throw CustomerException::customerNotFoundByHash($hash);
        }

        // reset login and pw-reset limit when password was changed
        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $cacheKey = strtolower((string) $customer->getEmail()) . '-' . $request->getClientIp();

            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $cacheKey);
            $this->rateLimiter->reset(RateLimiter::RESET_PASSWORD, $cacheKey);
        }

        $customerData = [
            'id' => $customer->getId(),
            'password' => $data->get('newPassword'),
            'legacyPassword' => null,
            'legacyEncoder' => null,
        ];

        $this->customerRepository->update([$customerData], $context->getContext());
        $this->deleteRecoveryForCustomer($customerRecovery, $context->getContext());

        return new SuccessResponse();
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateResetPassword(DataBag $data, SalesChannelContext $context): void
    {
        $definition = new DataValidationDefinition('customer.password.update');

        $minPasswordLength = $this->systemConfigService->get('core.loginRegistration.passwordMinLength', $context->getSalesChannel()->getId());

        $definition->add('newPassword', new NotBlank(), new Length(['min' => $minPasswordLength]), new EqualTo(['propertyPath' => 'newPasswordConfirm']));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualtoConstraint($data->all(), 'newPassword', $definition);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @param array<string|int, string> $data
     *
     * @throws ConstraintViolationException
     */
    private function tryValidateEqualtoConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        if (!\array_key_exists($field, $validations)) {
            return;
        }

        $fieldValidations = $validations[$field];

        /** @var EqualTo|null $equalityValidation */
        $equalityValidation = null;

        foreach ($fieldValidations as $emailValidation) {
            if ($emailValidation instanceof EqualTo) {
                $equalityValidation = $emailValidation;

                break;
            }
        }

        if (!$equalityValidation instanceof EqualTo) {
            return;
        }

        $compareValue = $data[$equalityValidation->propertyPath] ?? null;
        if ($data[$field] === $compareValue) {
            return;
        }

        $message = str_replace('{{ compared_value }}', $compareValue ?? '', (string) $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }

    private function deleteRecoveryForCustomer(CustomerRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->customerRecoveryRepository->delete([$recoveryData], $context);
    }

    private function checkHash(string $hash, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('hash', $hash)
        );

        $recovery = $this->customerRecoveryRepository->search($criteria, $context)->first();

        $validDateTime = (new \DateTime())->sub(new \DateInterval('PT2H'));

        return $recovery && $validDateTime < $recovery->getCreatedAt();
    }
}
