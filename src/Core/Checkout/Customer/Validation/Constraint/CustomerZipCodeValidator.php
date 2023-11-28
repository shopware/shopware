<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[Package('checkout')]
class CustomerZipCodeValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $countryRepository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerZipCode) {
            throw new UnexpectedTypeException($constraint, CustomerZipCodeValidator::class);
        }

        if ($constraint->countryId === null) {
            return;
        }

        $country = $this->getCountry($constraint->countryId);

        if ($country->getPostalCodeRequired()) {
            if ($value === null || $value === '') {
                $this->context->buildViolation($constraint->getMessageRequired())
                    ->setCode(NotBlank::IS_BLANK_ERROR)
                    ->addViolation();

                return;
            }
        }

        if (!$country->getCheckPostalCodePattern() && !$country->getCheckAdvancedPostalCodePattern()) {
            return;
        }

        $pattern = $country->getDefaultPostalCodePattern();

        if ($country->getCheckAdvancedPostalCodePattern()) {
            $pattern = $country->getAdvancedPostalCodePattern();
        }

        if ($pattern === null) {
            return;
        }

        $caseSensitive = $constraint->caseSensitiveCheck ? '' : 'i';

        if (preg_match("/^{$pattern}$/" . $caseSensitive, (string) $value, $matches) === 1) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ iso }}', $this->formatValue($country->getIso()))
            ->setCode(CustomerZipCode::ZIP_CODE_INVALID)
            ->addViolation();
    }

    private function getCountry(string $countryId): CountryEntity
    {
        $country = $this->countryRepository->search(new Criteria([$countryId]), Context::createDefaultContext())->get($countryId);

        if (!$country instanceof CountryEntity) {
            throw CustomerException::countryNotFound($countryId);
        }

        return $country;
    }
}
