<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Composer\Semver\Constraint\ConstraintInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\PasswordRecoveryUrlEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class SendPasswordRecoveryMailRoute extends AbstractSendPasswordRecoveryMailRoute
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

    public function getDecorated(): AbstractSendPasswordRecoveryMailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/recovery-password', name: 'store-api.account.recovery.send.mail', methods: ['POST'])]
    public function sendRecoveryMail(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true): SuccessResponse
    {
        $this->validateRecoverEmail($data, $context, $validateStorefrontUrl);

        if (($request = $this->requestStack->getMainRequest()) !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::RESET_PASSWORD, strtolower($data->get('email') . '-' . $request->getClientIp()));
        }

        $customer = $this->getCustomerByEmail($data->get('email'), $context);
        $customerId = $customer->getId();

        $customerIdCriteria = new Criteria();
        $customerIdCriteria->addFilter(new EqualsFilter('customerId', $customerId));
        $customerIdCriteria->addAssociation('customer.salutation');

        $repoContext = $context->getContext();

        if ($existingRecovery = $this->customerRecoveryRepository->search($customerIdCriteria, $repoContext)->first()) {
            $this->deleteRecoveryForCustomer($existingRecovery, $repoContext);
        }

        $recoveryData = [
            'customerId' => $customerId,
            'hash' => Random::getAlphanumericString(32),
        ];

        $this->customerRecoveryRepository->create([$recoveryData], $repoContext);

        $customerRecovery = $this->customerRecoveryRepository->search($customerIdCriteria, $repoContext)->first();

        $hash = $customerRecovery->getHash();

        $recoverUrl = $this->getRecoverUrl($context, $hash, $data->get('storefrontUrl'), $customerRecovery);

        $event = new CustomerAccountRecoverRequestEvent($context, $customerRecovery, $recoverUrl);
        $this->eventDispatcher->dispatch($event, CustomerAccountRecoverRequestEvent::EVENT_NAME);

        return new SuccessResponse();
    }

    private function validateRecoverEmail(DataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true): void
    {
        $validation = new DataValidationDefinition('customer.email.recover');

        $validation
            ->add(
                'email',
                new Email()
            );

        if ($validateStorefrontUrl) {
            $validation
                ->add('storefrontUrl', new NotBlank(), new Choice(array_values($this->getDomainUrls($context))));
        }

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
    }

    private function getDomainUrls(SalesChannelContext $context): array
    {
        return array_map(static fn (SalesChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $context->getSalesChannel()->getDomains()->getElements());
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @throws ConstraintViolationException
     */
    private function tryValidateEqualtoConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        if (!\array_key_exists($field, $validations)) {
            return;
        }

        /** @var array $fieldValidations */
        $fieldValidations = $validations[$field];

        /** @var EqualTo|null $equalityValidation */
        $equalityValidation = null;

        /** @var ConstraintInterface $emailValidation */
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

        $message = str_replace('{{ compared_value }}', $compareValue, (string) $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }

    private function getCustomerByEmail(string $email, SalesChannelContext $context): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.active', 1));
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        $criteria->addFilter(new EqualsFilter('customer.guest', 0));

        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('customer.boundSalesChannelId', null),
            new EqualsFilter('customer.boundSalesChannelId', $context->getSalesChannel()->getId()),
        ]));

        $result = $this->customerRepository->search($criteria, $context->getContext());

        if ($result->count() !== 1) {
            throw new CustomerNotFoundException($email);
        }

        return $result->first();
    }

    private function deleteRecoveryForCustomer(CustomerRecoveryEntity $existingRecovery, Context $context): void
    {
        $recoveryData = [
            'id' => $existingRecovery->getId(),
        ];

        $this->customerRecoveryRepository->delete([$recoveryData], $context);
    }

    private function getRecoverUrl(
        SalesChannelContext $context,
        string $hash,
        string $storefrontUrl,
        CustomerRecoveryEntity $customerRecovery
    ): string {
        $urlTemplate = $this->systemConfigService->get(
            'core.loginRegistration.pwdRecoverUrl',
            $context->getSalesChannelId()
        );
        if (!\is_string($urlTemplate)) {
            $urlTemplate = '/account/recover/password?hash=%%RECOVERHASH%%';
        }

        $urlEvent = new PasswordRecoveryUrlEvent($context, $urlTemplate, $hash, $storefrontUrl, $customerRecovery);
        $this->eventDispatcher->dispatch($urlEvent);

        return rtrim($storefrontUrl, '/') . str_replace(
            '%%RECOVERHASH%%',
            $hash,
            $urlEvent->getRecoveryUrl()
        );
    }
}
