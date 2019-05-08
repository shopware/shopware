<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Mapping\ImportMapper;

class ImportMapperTest extends TestCase
{
    use CustomerDefinition;

    /**
     * @var ImportMapper
     */
    private $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ImportMapper($this->buildDefinitionCollection());
    }

    public function testMappingCustomer(): void
    {
        $input = [
            'first_name' => 'foo',
            'last_name' => 'bar',
            'email' => 'foobar@example.com',
            'customer_number' => 'ABC-123',
            'sales_channel' => 'online',
            'birthday' => '04.02.1972',
            'salutation' => 'mr.',
            'default_payment_method' => 'SEPA',
            'customer_group' => 'default',
            'street' => 'Foostreet',
            'zip_code' => '12345',
            'city' => 'Bartown',
            'country' => 'DE',
            'active' => '0',
        ];
        $entityFormat = $this->mapper->map($input);

        static::assertEquals($input['first_name'], $entityFormat['firstName']);
        static::assertEquals($input['last_name'], $entityFormat['lastName']);
        static::assertEquals($input['email'], $entityFormat['email']);
        static::assertEquals($input['customer_number'], $entityFormat['customerNumber']);
        static::assertEquals($this->salesChannels['online'], $entityFormat['salesChannelId']);
        static::assertEquals($this->salutations['mr.'], $entityFormat['salutationId']);
        static::assertEquals($this->paymentMethods['SEPA'], $entityFormat['defaultPaymentMethodId']);
        static::assertEquals($this->customerGroups['default'], $entityFormat['groupId']);
        static::assertEquals($input['first_name'], $entityFormat['defaultBillingAddress']['firstName']);
        static::assertEquals($input['last_name'], $entityFormat['defaultBillingAddress']['lastName']);

        static::assertEquals($input['street'], $entityFormat['defaultBillingAddress']['street']);
        static::assertEquals($input['zip_code'], $entityFormat['defaultBillingAddress']['zipcode']);
        static::assertEquals($input['city'], $entityFormat['defaultBillingAddress']['city']);
        static::assertEquals($this->countries['DE'], $entityFormat['defaultBillingAddress']['countryId']);
        static::assertFalse($entityFormat['active']);
    }
}
