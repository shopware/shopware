<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerTransformer::class)]
class CustomerTransformerTest extends TestCase
{
    public function testCustomerTransformationWithCustomFields(): void
    {
        $customerId = Uuid::randomHex();

        $customer = $this->buildCustomerEntity($customerId);

        $customerData = CustomerTransformer::transform($customer);
        static::assertSame([
            'customerId' => $customerId,
            'email' => 'test@example.org',
            'firstName' => 'Max',
            'lastName' => 'Smith',
            'salutationId' => null,
            'title' => 'Dr.',
            'vatIds' => null,
            'company' => 'Acme Inc.',
            'customerNumber' => 'ABC123XY',
            'remoteAddress' => 'Test street 123, NY',
            'customFields' => ['customerGroup' => 'premium', 'origin' => 'newsletter', 'active' => true],
        ], $customerData);
    }

    #[DataProvider('buyerNameProvider')]
    public function testTransformResolvesTheBuyerName(
        string $accountType,
        string $firstName,
        string $lastName,
        ?string $company,
        string $expectedFirstName,
        string $expectedLastName
    ): void {
        $customer = $this->buildCustomerEntity(Uuid::randomHex());
        $customer->setAccountType($accountType);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCompany($company ?? '');

        $transformed = CustomerTransformer::transform($customer);

        static::assertSame($expectedFirstName, $transformed['firstName']);
        static::assertSame($expectedLastName, $transformed['lastName']);
    }

    public function testTransformDoesNotRequireAnAccountType(): void
    {
        $transformed = CustomerTransformer::transform($this->buildCustomerEntity(Uuid::randomHex()));

        static::assertSame('Max', $transformed['firstName']);
        static::assertSame('Smith', $transformed['lastName']);
    }

    /**
     * @return iterable<string, array{string, string, string, string|null, string, string}>
     */
    public static function buyerNameProvider(): iterable
    {
        yield 'company account without a contact person takes the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Acme GmbH', '', 'Acme GmbH',
        ];

        yield 'company account with a contact person keeps it' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', 'Acme GmbH', 'Ada', 'Lovelace',
        ];

        yield 'company account without a company keeps the empty name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', null, '', '',
        ];

        yield 'company account with a blank company keeps the empty name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', '   ', '', '',
        ];

        yield 'private account never takes the company' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, '', '', 'Acme GmbH', '', '',
        ];
    }

    private function buildCustomerEntity(string $id): CustomerEntity
    {
        $customerEntity = new CustomerEntity();
        $customerEntity->setId($id);
        $customerEntity->setEmail('test@example.org');
        $customerEntity->setFirstName('Max');
        $customerEntity->setLastName('Smith');
        $customerEntity->setTitle('Dr.');
        $customerEntity->setCompany('Acme Inc.');
        $customerEntity->setCustomerNumber('ABC123XY');
        $customerEntity->setRemoteAddress('Test street 123, NY');
        $customerEntity->setCustomFields(['customerGroup' => 'premium', 'origin' => 'newsletter', 'active' => true]);

        return $customerEntity;
    }
}
