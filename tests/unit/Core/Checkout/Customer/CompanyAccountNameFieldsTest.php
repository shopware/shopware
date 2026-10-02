<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CompanyAccountNameFields::class)]
class CompanyAccountNameFieldsTest extends TestCase
{
    #[DataProvider('configProvider')]
    public function testAreRequired(?bool $show, ?bool $required, bool $expected): void
    {
        static::assertSame($expected, $this->fields($show, $required)->areRequired(TestDefaults::SALES_CHANNEL));
    }

    public function testAreVisibleOnlyFollowsTheShowFlag(): void
    {
        static::assertTrue($this->fields(true, false)->areVisible(TestDefaults::SALES_CHANNEL));
        static::assertFalse($this->fields(false, true)->areVisible(TestDefaults::SALES_CHANNEL));
        static::assertTrue($this->fields(null, null)->areVisible(TestDefaults::SALES_CHANNEL));
    }

    #[DataProvider('accountTypeProvider')]
    public function testAreOptionalReadsTheRequestFirstAndTheCustomerSecond(?string $requestType, ?string $customerType, bool $expected): void
    {
        $data = new DataBag($requestType === null ? [] : ['accountType' => $requestType]);

        $customer = null;
        if ($customerType !== null) {
            $customer = new CustomerEntity();
            $customer->setAccountType($customerType);
        }

        static::assertSame($expected, $this->fields(true, false)->areOptional($data, $customer, TestDefaults::SALES_CHANNEL));
    }

    public function testAreOptionalIsFalseWhileTheNamesAreRequired(): void
    {
        $data = new DataBag(['accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS]);

        static::assertFalse($this->fields(true, true)->areOptional($data, null, TestDefaults::SALES_CHANNEL));
    }

    public function testNormalizeFillsMissingNames(): void
    {
        $data = new DataBag(['company' => 'Acme GmbH', 'lastName' => null]);

        $this->fields(true, false)->normalize($data);

        static::assertSame('', $data->get('firstName'));
        static::assertSame('', $data->get('lastName'));
    }

    public function testNormalizeSubmittedOnlyRewritesKeysTheRequestSent(): void
    {
        $data = new DataBag(['firstName' => null]);

        $this->fields(true, false)->normalize($data, submittedOnly: true);

        static::assertSame('', $data->get('firstName'));
        static::assertFalse($data->has('lastName'));
    }

    public function testMakeNamesOptionalKeepsEveryConstraintButNotBlankAndRequiresTheCompany(): void
    {
        $length = new Length(max: 10);
        $regex = new Regex(pattern: '/^[a-z]+$/');

        $validation = new DataValidationDefinition('test');
        $validation->add('firstName', new NotBlank(), $length, $regex);
        $validation->add('lastName', new NotBlank(), $length);

        $this->fields(true, false)->makeNamesOptional($validation, requireCompany: true);

        static::assertSame([$length, $regex], $validation->getProperty('firstName'));
        static::assertSame([$length], $validation->getProperty('lastName'));
        static::assertCount(1, $validation->getProperty('company'));
        static::assertInstanceOf(NotBlank::class, $validation->getProperty('company')[0]);
    }

    public function testMakeNamesOptionalCanLeaveTheCompanyAlone(): void
    {
        $validation = new DataValidationDefinition('test');
        $validation->add('firstName', new NotBlank());

        $this->fields(true, false)->makeNamesOptional($validation, requireCompany: false);

        static::assertSame([], $validation->getProperty('firstName'));
        static::assertSame([], $validation->getProperty('company'));
    }

    public function testMakeNamesOptionalLeavesUntouchedPropertiesAlone(): void
    {
        $validation = new DataValidationDefinition('test');

        $this->fields(true, false)->makeNamesOptional($validation, requireCompany: false);

        static::assertSame([], $validation->getProperties());
    }

    public function testCompanyNotBlankRejectsWhitespace(): void
    {
        $normalizer = CompanyAccountNameFields::companyNotBlank()->normalizer;

        static::assertIsCallable($normalizer);
        static::assertSame('', $normalizer('   '));
        static::assertNull($normalizer(null));
    }

    /**
     * @return iterable<string, array{bool|null, bool|null, bool}>
     */
    public static function configProvider(): iterable
    {
        yield 'shown and required' => [true, true, true];
        yield 'shown but optional' => [true, false, false];
        yield 'hidden cannot be required' => [false, true, false];
        yield 'hidden and optional' => [false, false, false];
        yield 'never saved keeps the names required' => [null, null, true];
        yield 'only the required flag saved' => [null, false, false];
        yield 'only the show flag saved' => [false, null, false];
    }

    /**
     * @return iterable<string, array{string|null, string|null, bool}>
     */
    public static function accountTypeProvider(): iterable
    {
        yield 'business request' => [CustomerEntity::ACCOUNT_TYPE_BUSINESS, null, true];
        yield 'private request' => [CustomerEntity::ACCOUNT_TYPE_PRIVATE, null, false];
        yield 'unknown request counts as private' => ['something-else', CustomerEntity::ACCOUNT_TYPE_BUSINESS, false];
        yield 'no request falls back to a business customer' => [null, CustomerEntity::ACCOUNT_TYPE_BUSINESS, true];
        yield 'empty request falls back to a business customer' => ['', CustomerEntity::ACCOUNT_TYPE_BUSINESS, true];
        yield 'no request falls back to a private customer' => [null, CustomerEntity::ACCOUNT_TYPE_PRIVATE, false];
        yield 'request wins over the customer' => [CustomerEntity::ACCOUNT_TYPE_BUSINESS, CustomerEntity::ACCOUNT_TYPE_PRIVATE, true];
        yield 'nothing at all' => [null, null, false];
    }

    private function fields(?bool $show, ?bool $required): CompanyAccountNameFields
    {
        return new CompanyAccountNameFields(new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                CompanyAccountNameFields::CONFIG_SHOW => $show,
                CompanyAccountNameFields::CONFIG_REQUIRED => $required,
            ],
        ]));
    }
}
