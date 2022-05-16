<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;

/**
 * @internal
 */
class CustomerZipcodeValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var DataValidator
     */
    private $validator;

    /**
     * @var DataValidationDefinition
     */
    private $validation;

    /**
     * @var CustomerZipCode
     */
    private $constraint;

    protected function setUp(): void
    {
        $this->validator = $this->getContainer()->get(DataValidator::class);
        $this->constraint = new CustomerZipCode([
            'countryId' => $this->getValidCountryId(),
        ]);
        $this->validation = new DataValidationDefinition('customer.create');
        $this->validation->add('zipcode', $this->constraint);
    }

    public function testValidateZipcodeWithoutEnabledValidation(): void
    {
        try {
            $this->validator->validate([
                'zipcode' => '1235468',
            ], $this->validation);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);
            static::assertNotEmpty($violation);
            static::assertEquals($this->constraint->message, $violation->getMessageTemplate());
        }
    }

    public function testValidateZipcodeIsRequiredWhenInputEmptyValue(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), ['postalCodeRequired' => true]);

        try {
            $this->validator->validate([
                'zipcode' => '',
            ], $this->validation);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);
            static::assertNotEmpty($violation);
            static::assertEquals($this->constraint->messageRequired, $violation->getMessageTemplate());
        }
    }

    public function testNonValidateZipcodeWhenTurnOffCheckPostalCodePattern(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), ['checkPostalCodePattern' => false]);

        $this->validator->validate([
            'zipcode' => '',
        ], $this->validation);
    }

    public function testValidZipcodeWhenUseDefaultValidation(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), [
            'checkPostalCodePattern' => true,
        ]);

        try {
            $this->validator->validate([
                'zipcode' => '12345',
            ], $this->validation);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);
            static::assertNotEmpty($violation);
            static::assertEquals($this->constraint->message, $violation->getMessageTemplate());
        }
    }

    public function testInvalidZipcodeWhenUseDefaultValidation(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), [
            'checkPostalCodePattern' => true,
        ]);

        try {
            $this->validator->validate([
                'zipcode' => 'abcdefghijk',
            ], $this->validation);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);
            static::assertNotEmpty($violation);
            static::assertEquals($this->constraint->message, $violation->getMessageTemplate());
        }
    }

    public function testValidZipcodeWithAdvancedValidationPattern(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), [
            'checkAdvancedPostalCodePattern' => true,
            'advancedPostalCodePattern' => '\\d{2}',
        ]);
        $this->validator->validate([
            'zipcode' => '12',
        ], $this->validation);
    }

    public function testStillValidZipcodeWhenUserInputWrongPatternFormat(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), [
            'checkAdvancedPostalCodePattern' => true,
            'advancedPostalCodePattern' => '[ahihi',
        ]);
        $this->validator->validate([
            'zipcode' => '12345',
        ], $this->validation);
    }

    public function testInvalidZipcodeWithAdvancedValidationPattern(): void
    {
        $this->upsertCountryAddressHandlingConfig($this->getValidCountryId(), [
            'checkAdvancedPostalCodePattern' => true,
            'advancedPostalCodePattern' => '\\d{2}',
        ]);

        try {
            $this->validator->validate([
                'zipcode' => '12c',
            ], $this->validation);
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $violations = $exception->getViolations();
            $violation = $violations->get(1);
            static::assertNotEmpty($violation);
            static::assertEquals($this->constraint->message, $violation->getMessageTemplate());
        }
    }

    private function upsertCountryAddressHandlingConfig(string $countryId, array $addressConfigs = []): void
    {
        $country = array_merge(['id' => $countryId], $addressConfigs);

        $this->getContainer()->get('country.repository')
            ->upsert([$country], Context::createDefaultContext());
    }
}
