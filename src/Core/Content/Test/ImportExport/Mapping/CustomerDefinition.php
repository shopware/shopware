<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Mapping\FieldDefinition;
use Shopware\Core\Content\ImportExport\Mapping\FieldDefinitionCollection;

trait CustomerDefinition
{
    private $salesChannels = [
        'online' => '6faca689711d45d791b5454ad8b9fb6d',
    ];

    private $salutations = [
        'mr.' => 'e4f2feadca5846e580d49f14bea50206',
    ];

    private $paymentMethods = [
        'SEPA' => '2445edf36b1f495db65798643155609f',
    ];

    private $customerGroups = [
        'default' => 'cfbd5018d38d41d8adca10d94fc8bdd6',
    ];

    private $countries = [
        'DE' => 'c83ea80c77114869aa15591cfaf19fd5',
    ];

    private function buildDefinitionCollection(): FieldDefinitionCollection
    {
        return new FieldDefinitionCollection([
            new FieldDefinition('first_name', 'firstName'),
            new FieldDefinition('last_name', 'lastName'),
            new FieldDefinition('email', 'email'),
            new FieldDefinition('customer_number', 'customerNumber'),
            new FieldDefinition('sales_channel', 'salesChannelId', $this->salesChannels),
            new FieldDefinition('birthday', 'birthday'),
            new FieldDefinition('salutation', 'salutationId', $this->salutations),
            new FieldDefinition('default_payment_method', 'defaultPaymentMethodId', $this->paymentMethods),
            new FieldDefinition('customer_group', 'groupId', $this->customerGroups),
            new FieldDefinition('first_name', 'defaultBillingAddress.firstName'),
            new FieldDefinition('last_name', 'defaultBillingAddress.lastName'),
            new FieldDefinition('salutation', 'defaultBillingAddress.salutationId', $this->salutations),
            new FieldDefinition('street', 'defaultBillingAddress.street'),
            new FieldDefinition('zip_code', 'defaultBillingAddress.zipcode'),
            new FieldDefinition('city', 'defaultBillingAddress.city'),
            new FieldDefinition('country', 'defaultBillingAddress.countryId', $this->countries),
            new FieldDefinition('active', 'active', [
                '0' => false,
                '1' => true,
            ]),
        ]);
    }
}
