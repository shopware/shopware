<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class CustomerEmailUniqueValidator extends ConstraintValidator
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(EntityRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerEmailUnique) {
            throw new UnexpectedTypeException($constraint, CustomerEmailUnique::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer.email', $value));
        $criteria->addFilter(new EqualsFilter('customer.guest', false));

        $result = $this->customerRepository->searchIds($criteria, $constraint->getContext());
        if ($result->getTotal() === 0) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ email }}', $this->formatValue($value))
            ->setCode(CustomerEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->addViolation();
    }
}
