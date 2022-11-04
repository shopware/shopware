<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class CustomerZipCodeValidator extends ConstraintValidator
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CustomerZipCode) {
            throw new UnexpectedTypeException($constraint, CustomerZipCodeValidator::class);
        }

        $addressConfigs = $this->getAddressConfig($constraint->countryId);

        if ($addressConfigs->getPostalCodeRequired()) {
            if ($value === null || $value === '') {
                $this->context->buildViolation($constraint->messageRequired)
                    ->setCode(NotBlank::IS_BLANK_ERROR)
                    ->addViolation();

                return;
            }
        }

        if (!$addressConfigs->getCheckPostalCodePattern() && !$addressConfigs->getCheckAdvancedPostalCodePattern()) {
            return;
        }

        $pattern = $addressConfigs->getAdvancedPostalCodePattern();
        $iso = $addressConfigs->getIso();

        if ($addressConfigs->getCheckPostalCodePattern() && !$addressConfigs->getCheckAdvancedPostalCodePattern()) {
            $pattern = $addressConfigs->getDefaultPostalCodePattern();
        }

        $caseSensitive = $constraint->caseSensitiveCheck ? '' : 'i';

        try {
            if ($pattern && !preg_match("/^{$pattern}$/" . $caseSensitive, $value, $matches)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ iso }}', $this->formatValue($iso))
                    ->setCode(CustomerZipCode::ZIP_CODE_INVALID)
                    ->addViolation();
            }
        } catch (\Exception $e) {
            return;
        }
    }

    private function getAddressConfig(string $countryId): CountryEntity
    {
        $country = $this->connection->fetchAssociative(
            'SELECT iso, postal_code_required, check_postal_code_pattern, check_advanced_postal_code_pattern, advanced_postal_code_pattern, default_postal_code_pattern FROM country WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($countryId)]
        );

        if (empty($country)) {
            throw new ConstraintDefinitionException(sprintf('Invalid country id "%s"', $countryId));
        }

        $addressConfig = new CountryEntity();
        $addressConfig->setIso($country['iso']);
        $addressConfig->setPostalCodeRequired((bool) $country['postal_code_required']);
        $addressConfig->setCheckPostalCodePattern((bool) $country['check_postal_code_pattern']);
        $addressConfig->setCheckAdvancedPostalCodePattern((bool) $country['check_advanced_postal_code_pattern']);
        $addressConfig->setAdvancedPostalCodePattern($country['advanced_postal_code_pattern']);
        $addressConfig->setDefaultPostalCodePattern($country['default_postal_code_pattern']);

        return $addressConfig;
    }
}
