<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerVatIdentification::class)]
class CustomerVatIdentificationTest extends TestCase
{
    public function testAnyEuCountryDefaultsToFalse(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex());

        static::assertFalse($constraint->getAnyEuCountry());
    }

    public function testAnyEuCountryCanBeEnabledViaNamedArgument(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex(), anyEuCountry: true);

        static::assertTrue($constraint->getAnyEuCountry());
    }

    public function testAnyEuCountryIsIndependentOfShouldCheck(): void
    {
        $constraint = new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            anyEuCountry: true,
        );

        static::assertTrue($constraint->getShouldCheck());
        static::assertTrue($constraint->getAnyEuCountry());
    }

    public function testTheDefaultMessageIsUnaffectedByTheNewArgument(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex(), anyEuCountry: true);

        static::assertSame('The format of vatId {{ vatId }} is not correct.', $constraint->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testAnyEuCountryDefaultsToFalseInTheLegacyOptionsArray(): void
    {
        $constraint = new CustomerVatIdentification(['countryId' => Uuid::randomHex()]);

        static::assertFalse($constraint->getAnyEuCountry());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testAnyEuCountryCanBeEnabledViaTheLegacyOptionsArray(): void
    {
        $constraint = new CustomerVatIdentification([
            'countryId' => Uuid::randomHex(),
            'anyEuCountry' => true,
        ]);

        static::assertTrue($constraint->getAnyEuCountry());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testTheLegacyOptionsArrayRejectsANonBooleanAnyEuCountry(): void
    {
        $this->expectExceptionObject(CustomerException::invalidOption('anyEuCountry', 'bool', CustomerVatIdentification::class));

        /** @phpstan-ignore argument.type (intentionally wrong option type for test purpose) */
        new CustomerVatIdentification([
            'countryId' => Uuid::randomHex(),
            'anyEuCountry' => 'yes',
        ]);
    }
}
