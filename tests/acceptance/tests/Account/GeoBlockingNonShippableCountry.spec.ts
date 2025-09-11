import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test.describe('@Storefront', {
  tag: ['@Account', '@Address'],
}, () => {

    test('Customers is able to to register an account and selects a non-shippable country for their billing address.', async ({
        StorefrontAccountLogin,
        StorefrontAccount,
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
    }) => {
        const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const shippableCountry = await TestDataService.getCountry('de');
        const registrationData = {
            salutation: 'Mr.',
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: `${nonShippableCountry.name} (Delivery not possible)`,
            state: 'Hamburg',
            postalCode: '48624',
        };

        await test.step('Customer cannot select non-shippable country for shipping address during registration', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.presses(StorefrontAccountLogin.countryInput, 'Space');
            await StorefrontAccountLogin.countryInput.selectOption({ label: registrationData.country });
            await ShopCustomer.expects(
            await StorefrontAccountLogin.getShippingCountryLocatorByName(registrationData.country)
            ).toBeDisabled();
        });

        await test.step('Customer submits the registration form successfully with a shippable country', async () => {
            await ShopCustomer.presses(StorefrontAccountLogin.salutationSelect, 'Space');
            await StorefrontAccountLogin.salutationSelect.selectOption(registrationData.salutation);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.firstNameInput, registrationData.firstName);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.lastNameInput, registrationData.lastName);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.registerEmailInput, customer.email);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.registerPasswordInput, registrationData.password);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.streetAddressInput, registrationData.street);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.postalCodeInput, registrationData.postalCode);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.cityInput, registrationData.city);
            await ShopCustomer.presses(StorefrontAccountLogin.countryInput, 'Space');
            await StorefrontAccountLogin.countryInput.selectOption({ label: shippableCountry.name });
            await ShopCustomer.presses(StorefrontAccountLogin.differentShippingAddressCheckbox, 'Space');
            await ShopCustomer.presses(StorefrontAccountLogin.shippingAddressSalutationSelect, 'Space');
            await StorefrontAccountLogin.shippingAddressSalutationSelect.selectOption(
                registrationData.salutation
            );
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressFirstNameInput, registrationData.firstName);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressLastNameInput, registrationData.lastName);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressStreetAddressInput, registrationData.street);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressPostalCodeInput, registrationData.postalCode);
            await ShopCustomer.fillsIn(StorefrontAccountLogin.shippingAddressCityInput, registrationData.city);
            await ShopCustomer.presses(StorefrontAccountLogin.shippingAddressCountryInput, 'Space');
            await StorefrontAccountLogin.shippingAddressCountryInput.selectOption({ label: shippableCountry.name });
            await ShopCustomer.presses(StorefrontAccountLogin.shippingAddressStateInput, 'Space');
            await StorefrontAccountLogin.shippingAddressStateInput.selectOption({ label: registrationData.state });
            await ShopCustomer.presses(StorefrontAccountLogin.registerButton, 'Enter');
            const customerId = (await TestDataService.getCustomerByEmail(customer.email)).id;
            TestDataService.addCreatedRecord('customer', customerId);
            await ShopCustomer.expects(StorefrontAccount.headline).toBeVisible();
        });
    });

    test('Customers is not able to set new shipping address with a non-shippable country.', async ({
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        StorefrontAccountLogin,
        Login,
        Logout,
        AddNewAddress,
        StorefrontAccount,
        StorefrontAccountAddresses,
        Register,
        InstanceMeta,
    }) => {
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false});
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);

        const address = {
            firstName: 'New First Name',
            lastName: 'New Last Name',
            company: 'shopware',
            department: 'Operations',
            street: 'Ebbinghof 10',
            zipCode: '48624',
            city: 'Schöppingen',
            country: nonShippableCountry.name,
        };

        const customer = { email: `${IdProvider.getIdPair().uuid}@test.com`, password: 'shopware', country: `${nonShippableCountry.name} (Delivery not possible)` };

        await test.step('Customer select non-shippable country during registration', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(customer));
            await ShopCustomer.expects(StorefrontAccount.cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('Customer see cannot deliver warning after re-login', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.expects(StorefrontAccount.cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('Customer add new address with non-shippable country and cannot set it as new shipping address', async () => {
            await ShopCustomer.goesTo(StorefrontAccountAddresses.url());
            await ShopCustomer.attemptsTo(AddNewAddress(address));
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(
                address.firstName + ' ' + address.lastName
            );
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(address.street);
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(address.city);
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(address.zipCode);
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(address.country);
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(address.company);
            await ShopCustomer.expects(StorefrontAccountAddresses.availableAddresses).toContainText(
                address.department
            );
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.expects(StorefrontAccountAddresses.useDefaultBillingAddressButton).toBeEnabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.useDefaultShippingAddressButton).toBeDisabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.deliveryNotPossibleAlert).toBeVisible();
            } else {
                await StorefrontAccountAddresses.addressDropdownButtons.last().click();
                await ShopCustomer.expects(StorefrontAccountAddresses.availableAddressesUseAsBillingAddress).toBeEnabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.availableAddressesUseAsShippingAddress).toBeDisabled();
            }

        });
    });
});