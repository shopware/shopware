<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Transformer\CustomerTransformer;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
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
