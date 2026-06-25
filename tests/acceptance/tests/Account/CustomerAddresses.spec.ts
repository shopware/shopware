import { test, Address } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('Make address default for billing.', { 
    tag: ['@Account', '@Address', '@Storefront'], 
    annotation: {type: 'story', description: 'As a shop customer I want to set a default billing address'},  
}, async ({
    InstanceMeta,
    ShopCustomer,
    StorefrontAccountAddresses,
    TestDataService,
    Login,
}) => {
    test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
    const customer = await TestDataService.createCustomer();
    const address = await TestDataService.createCustomerAddress(customer);
    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
    const addressContainer = await StorefrontAccountAddresses.getAvailableAddress(address);
    await ShopCustomer.presses(addressContainer.addressActions);
    await ShopCustomer.presses(addressContainer.useAsDefaultBillingButton);
    await ShopCustomer.expects(addressContainer.isDefaultBillingAddress).toBeVisible();

    const defaultBillingAddress = await StorefrontAccountAddresses.getDefaultBillingAddress(address);
    await ShopCustomer.expects(defaultBillingAddress).toBeVisible();
});

test('Make address default for shipping.', { 
    tag: ['@Account', '@Address', '@Storefront'], 
    annotation: {type: 'story', description: 'As a shop customer I want to set a default shipping address'},  
}, async ({
    InstanceMeta,
    ShopCustomer,
    StorefrontAccountAddresses,
    TestDataService,
    Login,
}) => {
    test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
    const customer = await TestDataService.createCustomer();
    const address = await TestDataService.createCustomerAddress(customer);
    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
    const addressContainer = await StorefrontAccountAddresses.getAvailableAddress(address);
    await ShopCustomer.presses(addressContainer.addressActions);
    await ShopCustomer.presses(addressContainer.useAsDefaultShippingButton);
    await ShopCustomer.expects(addressContainer.isDefaultShippingAddress).toBeVisible();

    const defaultShippingAddress = await StorefrontAccountAddresses.getDefaultShippingAddress(address);
    await ShopCustomer.expects(defaultShippingAddress).toBeVisible();
});

test('Edit an existing address.', { 
    tag: ['@Account', '@Address', '@Storefront'], 
    annotation: {type: 'story', description: 'As a shop customer I want to edit an existing address'},  
}, async ({
    InstanceMeta,
    ShopCustomer,
    StorefrontAccountAddresses,
    StorefrontAccountAddressDetails,
    TestDataService,
    Login,
}) => {
    test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
    const customer = await TestDataService.createCustomer();
    const address = await TestDataService.createCustomerAddress(customer);
    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
    const addressContainer = await StorefrontAccountAddresses.getAvailableAddress(address);
    await ShopCustomer.presses(addressContainer.addressActions);
    await ShopCustomer.presses(addressContainer.editAddressButton);

    await ShopCustomer.expects(StorefrontAccountAddressDetails.firstNameInput).toHaveValue(address.firstName);
    await ShopCustomer.expects(StorefrontAccountAddressDetails.lastNameInput).toHaveValue(address.lastName);
    await ShopCustomer.expects(StorefrontAccountAddressDetails.streetInput).toHaveValue(address.street);
    await ShopCustomer.expects(StorefrontAccountAddressDetails.zipcodeInput).toHaveValue(address.zipcode);
    await ShopCustomer.expects(StorefrontAccountAddressDetails.cityInput).toHaveValue(address.city);

    const newAddress: Partial<Address> = {
        firstName: 'Egon',
        lastName: 'Spengler',
        street: 'Ghostbusters Ave 10',
        zipcode: '54321',
        city: 'Manhattan',
    }
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.firstNameInput, newAddress.firstName);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.lastNameInput, newAddress.lastName);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.streetInput, newAddress.street);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.zipcodeInput, newAddress.zipcode);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.cityInput, newAddress.city);
    await ShopCustomer.presses(StorefrontAccountAddressDetails.saveAddressButton);

    const addressContainerEdited = await StorefrontAccountAddresses.getAvailableAddress(newAddress);
    await ShopCustomer.expects(addressContainerEdited.address).toBeVisible();
});

test('Create a new address.', {
    tag: ['@Account', '@Address', '@Storefront'],
    annotation: {type: 'story', description: 'As a shop customer I want to create a new address'},
}, async ({
    InstanceMeta,
    ShopCustomer,
    StorefrontAccountAddresses,
    StorefrontAccountAddressDetails,
    TestDataService,
    Login,
}) => {
    test.skip(satisfies(InstanceMeta.version, '<6.7'), 'Addresses were reworked in 6.7');
    const customer = await TestDataService.createCustomer();
    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
    await ShopCustomer.presses(StorefrontAccountAddresses.addNewAddressButton);
    const addressId = TestDataService.IdProvider.getIdPair();
    const newAddress: Partial<Address> = {
        id: addressId.uuid,
        firstName: 'Egon',
        lastName: 'Spengler',
        street: 'Ghostbusters Ave 10',
        zipcode: '54321',
        city: 'Manhattan',
    };
    TestDataService.addCreatedRecord('customer_address', newAddress.id);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.firstNameInput, newAddress.firstName);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.lastNameInput, newAddress.lastName);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.streetInput, newAddress.street);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.zipcodeInput, newAddress.zipcode);
    await ShopCustomer.fillsIn(StorefrontAccountAddressDetails.cityInput, newAddress.city);
    await ShopCustomer.presses(StorefrontAccountAddressDetails.saveAddressButton);

    const addressContainer = await StorefrontAccountAddresses.getAvailableAddress(newAddress);
    await ShopCustomer.expects(addressContainer.address).toBeVisible();
}); 
