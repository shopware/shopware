<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Composer\Semver\Constraint\ConstraintInterface;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByHashException;
use Shopware\Core\Checkout\Customer\Exception\CustomerRecoveryHashExpiredException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ResetPasswordRoute extends AbstractResetPasswordRoute
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

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerRecoveryRepository,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator,
        SystemConfigService $systemConfigService
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerRecoveryRepository = $customerRecoveryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractResetPasswordRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/recovery-password-confirm",
     *      description="Resets password using recovery hash",
     *      operationId="recoveryPassword",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *        name="hash",
     *        in="body",
     *        description="Hash from confirmation mail",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *        name="newPassword",
     *        in="body",
     *        description="new password",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *        name="newPasswordConfirm",
     *        in="body",
     *        description="new password",
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
     *          description=""
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/recovery-password-confirm", name="store-api.account.recovery.password", methods={"POST"})
     */
    public function resetPassword(RequestDataBag $data, SalesChannelContext $context): SuccessResponse
    {
        $this->validateResetPassword($data, $context);

        $hash = $data->get('hash');

        if (!$this->checkHash($hash, $context->getContext())) {
            throw new CustomerRecoveryHashExpiredException($hash);
        }

        $customerHashCriteria = new Criteria();
        $customerHashCriteria->addFilter(new EqualsFilter('hash', $hash));
        $customerHashCriteria->addAssociation('customer');

        $customerRecovery = $this->customerRecoveryRepository->search($customerHashCriteria, $context->getContext())->first();

        if (!$customerRecovery) {
            throw new CustomerNotFoundByHashException($hash);
        }

        $customer = $customerRecovery->getCustomer();

        if (!$customer) {
            throw new CustomerNotFoundByHashException($hash);
        }

        $customerData = [
            'id' => $customer->getId(),
            'password' => $data->get('newPassword'),
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

        $this->dispatchValidationEvent($definition, $context->getContext());

        $this->validator->validate($data->all(), $definition);

        $this->tryValidateEqualtoConstraint($data->all(), 'newPassword', $definition);
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
