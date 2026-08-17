<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\EuVatIdPatternProvider;
use Shopware\Core\Checkout\Customer\Validation\VatIdPattern;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('checkout')]
class CustomerVatIdentificationValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EuVatIdPatternProvider $euVatIdPatternProvider,
    ) {
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

        $vatIdInformation = $this->connection->fetchAssociative(
            'SELECT iso, check_vat_id_pattern, vat_id_pattern FROM `country` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($constraint->getCountryId())]
        );

        if ($vatIdInformation === false) {
            return;
        }

        \assert(\array_key_exists('iso', $vatIdInformation));
        \assert(\array_key_exists('check_vat_id_pattern', $vatIdInformation));
        \assert(\array_key_exists('vat_id_pattern', $vatIdInformation));

        if (!$constraint->getShouldCheck() && !$vatIdInformation['check_vat_id_pattern']) {
            return;
        }

        $pattern = (string) $vatIdInformation['vat_id_pattern'];
        $countryPattern = $pattern === '' ? null : new VatIdPattern((string) $vatIdInformation['iso'], $pattern);
        $anyEuCountry = $constraint->getAnyEuCountry();

        if ($countryPattern === null && !$anyEuCountry) {
            return;
        }

        foreach ($vatIds as $vatId) {
            if ($this->isValid((string) $vatId, $countryPattern, $anyEuCountry)) {
                continue;
            }

            $this->context->buildViolation($constraint->getMessage())
                ->setParameter('{{ vatId }}', $this->formatValue($vatId))
                ->setCode(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT)
                ->addViolation();
        }
    }

    private function isValid(string $vatId, ?VatIdPattern $countryPattern, bool $anyEuCountry): bool
    {
        if ($countryPattern?->matches($vatId)) {
            return true;
        }

        return $anyEuCountry && $this->euVatIdPatternProvider->matchVatId($vatId) !== null;
    }
}
