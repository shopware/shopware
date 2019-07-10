<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Mapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Mapping\ExportMapper;
use Shopware\Core\Content\ImportExport\Mapping\FieldDefinitionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class ExportMapperTest extends TestCase
{
    use CustomerDefinition;
    use KernelTestBehaviour;

    /**
     * @var ExportMapper
     */
    private $mapper;

    /**
     * @var FieldDefinitionCollection
     */
    private $fieldDefinitions;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $entityDefinitionRegistry;

    protected function setUp(): void
    {
        $this->entityDefinitionRegistry = new DefinitionInstanceRegistry(
            $this->getContainer(),
            ['customer' => \Shopware\Core\Checkout\Customer\CustomerDefinition::class],
            ['customer' => 'customer.repository']
        );
        $this->mapper = new ExportMapper();
        $this->fieldDefinitions = $this->buildDefinitionCollection();
    }

    public function testMappingCustomer(): void
    {
        $input = [
            'firstName' => 'foo',
            'lastName' => 'bar',
            'email' => 'foobar@example.com',
            'customerNumber' => 'ABC-123',
            'salesChannelId' => $this->salesChannels['online'],
            'birthday' => '1972-02-04',
            'salutationId' => $this->salutations['mr.'],
            'defaultPaymentMethodId' => $this->paymentMethods['SEPA'],
            'groupId' => $this->customerGroups['default'],
            'defaultBillingAddress' => [
                'firstName' => 'foo',
                'lastName' => 'bar',
                'street' => 'Foostreet',
                'zipcode' => '12345',
                'city' => 'Bartown',
                'countryId' => $this->countries['DE'],
            ],
            'active' => false,
        ];
        $fileFormat = $this->mapper->map(
            $input,
            $this->fieldDefinitions,
            $this->entityDefinitionRegistry->getByEntityName('customer')
        );

        static::assertEquals($input['firstName'], $fileFormat['first_name']);
        static::assertEquals($input['lastName'], $fileFormat['last_name']);
        static::assertEquals($input['email'], $fileFormat['email']);
        static::assertEquals($input['customerNumber'], $fileFormat['customer_number']);
        static::assertEquals('online', $fileFormat['sales_channel']);
        static::assertEquals('mr.', $fileFormat['salutation']);
        static::assertEquals('SEPA', $fileFormat['default_payment_method']);
        static::assertEquals('default', $fileFormat['customer_group']);
        static::assertEquals($input['defaultBillingAddress']['firstName'], $fileFormat['first_name']);
        static::assertEquals($input['defaultBillingAddress']['lastName'], $fileFormat['last_name']);

        static::assertEquals($input['defaultBillingAddress']['street'], $fileFormat['street']);
        static::assertEquals($input['defaultBillingAddress']['zipcode'], $fileFormat['zip_code']);
        static::assertEquals($input['defaultBillingAddress']['city'], $fileFormat['city']);
        static::assertEquals('DE', $fileFormat['country']);
        static::assertEquals('0', $fileFormat['active']);
    }
}
