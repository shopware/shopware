<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('checkout')]
class CustomerVatIdentificationValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly VatIdPatternProvider $vatIdPatternProvider)
    {
    }

    public function validate(mixed $vatIds, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerVatIdentification) {
            throw CustomerException::unexpectedConstraintType($constraint, CustomerVatIdentification::class);
        }

        if ($vatIds === null) {
            return;
        }

        if (!is_iterable($vatIds)) {
            throw CustomerException::unexpectedConstraintValue('iterable', CustomerVatIdentification::class);
        }

        $country = $this->vatIdPatternProvider->getCountrySettings($constraint->getCountryId());

        if ($country === null) {
            return;
        }

        if (!$constraint->getShouldCheck() && !$country['checkPattern']) {
            return;
        }

        if ($country['pattern'] === null) {
            return;
        }

        foreach ($vatIds as $vatId) {
            if ($this->isValid((string) $vatId, $country['pattern'], $country['isEu'])) {
                continue;
            }

            $this->context->buildViolation($constraint->getMessage())
                ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                ->setCode(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                ->addViolation();
        }
    }

    /**
     * An intra-EU B2B supply is tax free because the customer holds a VAT ID of some member state, not
     * because that state is the one being validated against, so a member state accepts the pattern of
     * every other one. Outside the EU there is no such union, so only the country's own pattern counts.
     */
    private function isValid(string $vatId, string $countryPattern, bool $isEu): bool
    {
        if ($this->vatIdPatternProvider->matches($countryPattern, $vatId)) {
            return true;
        }

        return $isEu && $this->vatIdPatternProvider->getStateByEuVatId($vatId) !== null;
    }
}
