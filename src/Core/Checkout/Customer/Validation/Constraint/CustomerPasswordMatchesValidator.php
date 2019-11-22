<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class CustomerPasswordMatchesValidator extends ConstraintValidator
{
    /**
     * @var AccountService
     */
    private $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function validate($password, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerPasswordMatches) {
            return;
        }

        $context = $constraint->getContext();

        try {
            $email = $context->getCustomer()->getEmail();

            $this->accountService->getCustomerByLogin($email, (string) $password, $constraint->getContext());

            return;
        } catch (BadCredentialsException $exception) {
            $this->context->buildViolation($constraint->message)
                ->setCode(CustomerPasswordMatches::CUSTOMER_PASSWORD_NOT_CORRECT)
                ->addViolation();
        }
    }
}
