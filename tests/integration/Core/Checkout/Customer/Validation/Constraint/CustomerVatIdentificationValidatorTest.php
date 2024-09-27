<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Validation\Constraint;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\HappyPathValidator;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CustomerVatIdentificationValidator::class)]
class CustomerVatIdentificationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const COUNTRY_ISO = [
        'DE', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'GR', 'ES', 'FI', 'FR', 'GB', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    private CustomerVatIdentificationValidator $validator;

    private ExecutionContext $executionContext;

    /**
     * @var string[]
     */
    private readonly array $countries;

    protected function setUp(): void
    {
        $this->countries = $this->getCountries();

        $this->executionContext = new ExecutionContext(
            $this->getContainer()->get(HappyPathValidator::class),
            null,
            $this->getContainer()->get(TranslatorInterface::class),
        );

        $connection = $this->getContainer()->get(Connection::class);

        $this->validator = new CustomerVatIdentificationValidator($connection);

        $this->validator->initialize($this->executionContext);
    }

    /**
     * @param array<int, string> $vatIds
     */
    #[DataProvider('dataProviderValidatesVatIdsCorrectly')]
    public function testValidatesVatIdsCorrectly(string $iso, array $vatIds): void
    {
        $constraint = new CustomerVatIdentification([
            'message' => 'Invalid VAT ID',
            'countryId' => $this->countries[$iso],
            'shouldCheck' => true,
        ]);

        $this->validator->validate($vatIds, $constraint);

        static::assertCount(0, $this->executionContext->getViolations());
    }

    /**
     * @param array<int, string> $vatIds
     */
    #[DataProvider('dataProviderValidatesVatIdsInCorrectly')]
    public function testValidateVatIdsInCorrectly(string $iso, int $count, array $vatIds): void
    {
        $constraint = new CustomerVatIdentification([
            'message' => 'Invalid VAT ID',
            'countryId' => $this->countries[$iso],
            'shouldCheck' => true,
        ]);

        $this->validator->validate($vatIds, $constraint);

        /** @var ConstraintViolationList $violations */
        $violations = $this->executionContext->getViolations();

        static::assertSame($violations->count(), $count);

        static::assertNotNull($violation = $violations->get(0));
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        static::assertEquals('Invalid VAT ID', $violation->getMessage());
        static::assertEquals($violation->getParameters(), ['{{ vatId }}' => '"' . $vatIds[0] . '"']);
        static::assertEquals(CustomerVatIdentification::VAT_ID_FORMAT_NOT_CORRECT, $violation->getCode());
    }

    public function testDoesNotValidateWhenVatIdsIsNull(): void
    {
        $constraint = new CustomerVatIdentification([
            'message' => 'Invalid VAT ID',
            'countryId' => $this->countries['DE'],
            'shouldCheck' => true,
        ]);

        $this->validator->validate(null, $constraint);

        static::assertCount(0, $this->executionContext->getViolations());
    }

    public function testDoesNotValidateWhenShouldCheckIsFalse(): void
    {
        $constraint = new CustomerVatIdentification([
            'message' => 'Invalid VAT ID',
            'countryId' => $this->countries['DE'],
            'shouldCheck' => false,
        ]);

        $this->validator->validate(['DE123456789'], $constraint);

        static::assertCount(0, $this->executionContext->getViolations());
    }

    /**
     * @return iterable<string, array<int, array<int, string>|string>>
     */
    public static function dataProviderValidatesVatIdsCorrectly(): iterable
    {
        yield 'valid vat with Austria' => ['AT', ['ATU12345678', 'ATU87654321', 'ATU23456789']];

        yield 'valid vat with Germany' => ['DE', ['DE123456789', 'DE999999999', 'DE888888888']];

        yield 'valid vat with Belgium' => ['BE', ['BE0123456789']];

        yield 'valid vat with Bulgaria' => ['BG', ['BG1234567890', 'BG123456789']];

        yield 'valid vat with Cyprus' => ['CY', ['CY12345678L']];

        yield 'valid vat with Czech Republic' => ['CZ', ['CZ12345678', 'CZ123456789', 'CZ1234567890']];

        yield 'valid vat with Denmark' => ['DK', ['DK12345678']];

        yield 'valid vat with Estonia' => ['EE', ['EE123456789', 'EE987654321']];

        yield 'valid vat with Spain' => ['ES', ['ESX1234567R', 'ES12345678X']];

        yield 'valid vat with Finland' => ['FI', ['FI12345674']];

        yield 'valid vat with France' => ['FR', ['FR12345678901', 'FRX1234567890', 'FR1X123456789', 'FRXX123456789']];

        yield 'valid vat with Hungary' => ['HU', ['HU12345678']];

        yield 'valid vat with Ireland' => ['IE', ['IE1234567T', 'IE1234567FA']];

        yield 'valid vat with Italy' => ['IT', ['IT12345678901', 'IT09876543210']];

        yield 'valid vat with Lithuania' => ['LT', ['LT123456789', 'LT9876543210', 'LT123456789012']];

        yield 'valid vat with Luxembourg' => ['LU', ['LU12345678', 'LU87654321']];

        yield 'valid vat with Latvia' => ['LV', ['LV12345678901', 'LV98765432109', 'LV34567891234']];

        yield 'valid vat with Malta' => ['MT', ['MT12345678']];

        yield 'valid vat with Netherlands' => ['NL', ['NL123456789B01', 'NL999999999B99']];

        yield 'valid vat with Poland' => ['PL', ['PL0123456789']];

        yield 'valid vat with Portugal' => ['PT', ['PT123456789', 'PT987654321']];

        yield 'valid vat with Romania' => ['RO', ['RO1234567890', 'RO123456', 'RO12']];

        yield 'valid vat with Sweden' => ['SE', ['SE123456789901', 'SE987654321902', 'SE345678912303']];

        yield 'valid vat with Slovenia' => ['SI', ['SI12345678']];

        yield 'valid vat with Slovakia' => ['SK', ['SK1234567890']];
    }

    /**
     * @return iterable<string, array<int, array<int, string>|int|string>>
     */
    public static function dataProviderValidatesVatIdsInCorrectly(): iterable
    {
        yield 'invalid vat with Germany' => [
            'DE',
            5,
            ['AADE1234567', '123456789', 'DE12345678', 'DEC123456789', '123456789DE', 'DE123456789'],
        ];

        yield 'invalid vat with Austria' => [
            'AT',
            3,
            ['AT12345678', 'ATU1234567', 'ATU123456789'],
        ];

        yield 'invalid vat with Belgium' => [
            'BE',
            3,
            ['BE09999999XX', 'BE123456789', 'BE09999999YY'],
        ];

        yield 'invalid vat with Bulgaria' => [
            'BG',
            5,
            ['BG01234567', 'BG01234567890', 'BX123456789012', 'BGAA99999999', 'BGAA123456'],
        ];

        yield 'invalid vat with Cyprus' => [
            'CY',
            4,
            ['CY12345678Y', 'CY123456789', 'CY12345678', 'CY12345678X'],
        ];

        yield 'invalid vat with Czech Republic' => [
            'CZ',
            4,
            ['CZ1234567', 'CZ12345678901', 'CZ12345678901', 'CZ12345678901X'],
        ];

        yield 'invalid vat with Denmark' => [
            'DK',
            3,
            ['DK1234567', 'DK12345678901', 'DK12345678901'],
        ];

        yield 'invalid vat with Estonia' => [
            'EE',
            3,
            ['12345678', '1234567890', '12345678901'],
        ];

        yield 'invalid vat with Spain' => [
            'ES',
            4,
            ['ES12345678', 'ES12345678901', 'ES12345678901', 'ES12345678901X'],
        ];

        yield 'invalid vat with Finland' => [
            'FI',
            3,
            ['FI1234567', 'FI12345678901', 'FI12345678901'],
        ];

        yield 'invalid vat with France' => [
            'FR',
            6,
            ['FR1234567890', 'FRAB12345678', 'FR12A34567', 'FR!2A3456789', 'FRABCDE123456789', 'FRA12B3456789'],
        ];

        yield 'invalid vat with Hungary' => [
            'HU',
            3,
            ['HU1234567', 'HU12345678901', 'HU12345678901'],
        ];

        yield 'invalid vat with Ireland' => [
            'IE',
            4,
            ['IE1234567', 'IE12345678901', 'IE12345678901', 'IE12345678901X'],
        ];

        yield 'invalid vat with Italy' => [
            'IT',
            3,
            ['1234567890', '123456789012', '1234567890123'],
        ];

        yield 'invalid vat with Lithuania' => [
            'LT',
            3,
            ['12345678', '1234567890', '1234567890123'],
        ];

        yield 'invalid vat with Luxembourg' => [
            'LU',
            3,
            ['1234567', '1234567890', '12345678901'],
        ];

        yield 'invalid vat with Latvia' => [
            'LV',
            2,
            ['123456789', '123456789012'],
        ];

        yield 'invalid vat with Malta' => [
            'MT',
            2,
            ['1234567', '1234567890'],
        ];

        yield 'invalid vat with Netherlands' => [
            'NL',
            3,
            ['NL123456789B0', 'NL123456789B012', 'NL123456789B0123'],
        ];

        yield 'invalid vat with Poland' => [
            'PL',
            3,
            ['PL12345678', 'PL12345678901', 'PL12345678901'],
        ];

        yield 'invalid vat with Portugal' => [
            'PT',
            3,
            ['12345678', '1234567890', '12345678901'],
        ];

        yield 'invalid vat with Romania' => [
            'RO',
            3,
            ['RO1', 'RO12345678901', 'ROXY12345678'],
        ];

        yield 'invalid vat with Sweden' => [
            'SE',
            3,
            ['SE1234567890', 'SE1234567899010', 'SE1234567899'],
        ];

        yield 'invalid vat with Slovenia' => [
            'SI',
            3,
            ['SI1234567', 'SI12345678901', 'SI12345678901'],
        ];

        yield 'invalid vat with Slovakia' => [
            'SK',
            3,
            ['SK123456789', 'SK12345678901', 'SK12345678901'],
        ];
    }

    /**
     * @return array<string>
     */
    private function getCountries(): array
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(\count(self::COUNTRY_ISO));

        $criteria->addFilter(new EqualsAnyFilter('iso', self::COUNTRY_ISO));

        $repo = $this->getContainer()->get('country.repository');

        $countries = $repo->search($criteria, $context)->fmap(function (CountryEntity $country) {
            return $country->getIso();
        });

        return array_flip($countries);
    }
}
