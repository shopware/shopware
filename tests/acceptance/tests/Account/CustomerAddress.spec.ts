import { test } from '@fixtures/AcceptanceTest';

test('Customers are able to add new addresses and swap the roles these two addreses.', { tag: '@Addresses @Account' }, async ({
    ShopCustomer,
    StorefrontAccountAddresses,
    TestDataService,
    Login,
    StorefrontAccountAddressCreate,
}) => {

    const customer = await TestDataService.createCustomer();

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
    await StorefrontAccountAddresses.addNewAddressButton.click();
    await StorefrontAccountAddressCreate.salutationDropdown.selectOption({ label: 'Mr.' });
    await StorefrontAccountAddressCreate.firstNameInput.fill('P.');
    await StorefrontAccountAddressCreate.lastNameInput.fill('Sherman');
    await StorefrontAccountAddressCreate.companyInput.fill('Pixar Inc.');
    await StorefrontAccountAddressCreate.departmentInput.fill('Animation');
    await StorefrontAccountAddressCreate.streetInput.fill('42 Wallaby Way');

    await StorefrontAccountAddressCreate.zipcodeInput.fill('2000');
    await StorefrontAccountAddressCreate.cityInput.fill('Sydney');
    await StorefrontAccountAddressCreate.countryDropdown.waitFor({ state: 'visible' });
    await StorefrontAccountAddressCreate.countryDropdown.selectOption('Australia' );
    //await StorefrontAccountAddressCreate.saveAddressButton.click();
});