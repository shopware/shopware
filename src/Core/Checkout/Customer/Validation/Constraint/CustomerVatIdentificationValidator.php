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
            $accepted = $this->vatIdPatternProvider->acceptsVatId(
                (string) $vatId,
                $country['pattern'],
                $country['isEu'],
                $constraint->getSalesChannelId()
            );

            if ($accepted) {
                continue;
            }

            $this->context->buildViolation($constraint->getMessage())
                ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                ->setCode(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                ->addViolation();
        }
    }
}
