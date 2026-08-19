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
    public function testMatchesAnyEuVatDefaultsToFalse(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex());

        static::assertFalse($constraint->getMatchesAnyEuVat());
    }

    public function testMatchesAnyEuVatCanBeEnabledViaNamedArgument(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex(), matchesAnyEuVat: true);

        static::assertTrue($constraint->getMatchesAnyEuVat());
    }

    public function testMatchesAnyEuVatIsIndependentOfShouldCheck(): void
    {
        $constraint = new CustomerVatIdentification(
            countryId: Uuid::randomHex(),
            shouldCheck: true,
            matchesAnyEuVat: true,
        );

        static::assertTrue($constraint->getShouldCheck());
        static::assertTrue($constraint->getMatchesAnyEuVat());
    }

    public function testTheDefaultMessageIsUnaffectedByTheNewArgument(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex(), matchesAnyEuVat: true);

        static::assertSame('The format of vatId {{ vatId }} is not correct.', $constraint->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testMatchesAnyEuVatDefaultsToFalseInTheLegacyOptionsArray(): void
    {
        $constraint = new CustomerVatIdentification(['countryId' => Uuid::randomHex()]);

        static::assertFalse($constraint->getMatchesAnyEuVat());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testMatchesAnyEuVatCanBeEnabledViaTheLegacyOptionsArray(): void
    {
        $constraint = new CustomerVatIdentification([
            'countryId' => Uuid::randomHex(),
            'matchesAnyEuVat' => true,
        ]);

        static::assertTrue($constraint->getMatchesAnyEuVat());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[IgnoreDeprecations]
    public function testTheLegacyOptionsArrayRejectsANonBooleanMatchesAnyEuVat(): void
    {
        $this->expectExceptionObject(CustomerException::invalidOption('matchesAnyEuVat', 'bool', CustomerVatIdentification::class));

        /** @phpstan-ignore argument.type (intentionally wrong option type for test purpose) */
        new CustomerVatIdentification([
            'countryId' => Uuid::randomHex(),
            'matchesAnyEuVat' => 'yes',
        ]);
    }
}
