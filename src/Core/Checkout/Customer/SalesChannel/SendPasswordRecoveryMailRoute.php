<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Composer\Semver\Constraint\ConstraintInterface;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SendPasswordRecoveryMailRoute extends AbstractSendPasswordRecoveryMailRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRecoveryRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DataValidator
     */
    private $validator;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerRecoveryRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function getDecorated(): AbstractSendPasswordRecoveryMailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/recovery-password",
     *      description="Sends a recovery email for password recovery",
     *      operationId="sendRecoveryMail",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *        name="email",
     *        in="body",
     *        description="Email",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *        name="storefrontUrl",
     *        in="body",
     *        description="baseurl for the url in mail",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(ref="#/definitions/SuccessResponse")
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/recovery-password", name="store-api.account.recovery.send.mail", methods={"POST"})
     */
    public function sendRecoveryMail(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true): SuccessResponse
    {
        $this->validateRecoverEmail($data, $context);

        $customer = $this->getCustomerByEmail($data->get('email'), $context);
        $customerId = $customer->getId();

        $customerIdCriteria = new Criteria();
        $customerIdCriteria->addFilter(new EqualsFilter('customerId', $customerId));
        $customerIdCriteria->addAssociation('customer');

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
        $recoverUrl = $data->get('storefrontUrl') . '/account/recover/password?hash=' . $hash;

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

        $this->dispatchValidationEvent($validation, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
    }

    private function getDomainUrls(SalesChannelContext $context): array
    {
        return array_map(static function (SalesChannelDomainEntity $domainEntity) {
            return $domainEntity->getUrl();
        }, $context->getSalesChannel()->getDomains()->getElements());
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @throws ConstraintViolationException
     */
    private function tryValidateEqualtoConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        if (!array_key_exists($field, $validations)) {
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

        $message = str_replace('{{ compared_value }}', $compareValue, $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }

    private function getCustomerByEmail(string $email, SalesChannelContext $context): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $email));
        $criteria->addFilter(new EqualsFilter('customer.guest', 0));

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
}
