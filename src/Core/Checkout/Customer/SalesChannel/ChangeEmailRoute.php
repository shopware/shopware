<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Composer\Semver\Constraint\ConstraintInterface;
use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ChangeEmailRoute extends AbstractChangeEmailRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

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
        EventDispatcherInterface $eventDispatcher,
        DataValidator $validator
    ) {
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function getDecorated(): AbstractChangeEmailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/change-email",
     *      description="Change email",
     *      operationId="changeEmail",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *        name="email",
     *        in="body",
     *        description="New Email",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *        name="emailConfirmation",
     *        in="body",
     *        description="New Email",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Parameter(
     *        name="password",
     *        in="body",
     *        description="Current password",
     *        @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(ref="#/definitions/SuccessResponse")
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/change-email", name="store-api.account.change-email", methods={"POST"})
     */
    public function change(RequestDataBag $requestDataBag, SalesChannelContext $context): SuccessResponse
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $this->validateEmail($requestDataBag, $context);

        $customerData = [
            'id' => $context->getCustomer()->getId(),
            'email' => $requestDataBag->get('email'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function validateEmail(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.email.update');

        $validation
            ->add(
                'email',
                new Email(),
                new EqualTo(['propertyPath' => 'emailConfirmation']),
                new CustomerEmailUnique(['context' => $context->getContext()])
            )
            ->add('password', new CustomerPasswordMatches(['context' => $context]));

        $this->dispatchValidationEvent($validation, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualtoConstraint($data->all(), 'email', $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

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
}
