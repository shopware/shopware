import { test } from '@fixtures/AcceptanceTest';
import { deleteRegisteredUser, RegistrationData } from '@shopware-ag/acceptance-test-suite';
import { satisfies } from 'compare-versions';

test(
    'Customers is able to to register an account and selects a non-shippable country for their billing address.',
    { tag: '@Account @Address' },
    async ({
        StorefrontAccountLogin,
        StorefrontAccount,
        IdProvider,
        ShopCustomer,
        TestDataService,
        DefaultSalesChannel,
        AdminApiContext,
    }) => {
        const customer = { email: IdProvider.getIdPair().uuid + '@test.com' };
        const nonShippableCountry = await TestDataService.createCountry({ shippingAvailable: false });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const shippableCountry = await TestDataService.createCountry({
            states: [
                {
                    name: 'California',
                    shortCode: 'CA',
                },
            ],
        });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, shippableCountry.id);
        const defaultRegistrationData: RegistrationData = {
            isCommercial: false,
            isGuest: false,
            salutation: 'Mr.',
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: `${nonShippableCountry.name} (Delivery not possible)`,
            postalCode: '48624',
            company: 'shopware',
            department: 'Operations',
            vatRegNo: 'DE1234567890',
        };

        await test.step('Customer cannot select non-shippable country for shipping address during registration', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await StorefrontAccountLogin.salutationSelect.selectOption(defaultRegistrationData.salutation);
            await StorefrontAccountLogin.firstNameInput.fill(defaultRegistrationData.firstName);
            await StorefrontAccountLogin.lastNameInput.fill(defaultRegistrationData.lastName);
            await StorefrontAccountLogin.registerEmailInput.fill(customer.email);
            await StorefrontAccountLogin.registerPasswordInput.fill(defaultRegistrationData.password);
            await StorefrontAccountLogin.streetAddressInput.fill(defaultRegistrationData.street);
            await StorefrontAccountLogin.postalCodeInput.fill(defaultRegistrationData.postalCode);
            await StorefrontAccountLogin.cityInput.fill(defaultRegistrationData.city);
            await StorefrontAccountLogin.countryInput.selectOption({ label: defaultRegistrationData.country });
            await StorefrontAccountLogin.differentShippingAddressCheckbox.check();
            await StorefrontAccountLogin.shippingAddressSalutationSelect.selectOption(
                defaultRegistrationData.salutation
            );
            await ShopCustomer.expects(
                await StorefrontAccountLogin.getShippingCountryLocatorByName(defaultRegistrationData.country)
            ).toBeDisabled();
        });

        await test.step('Customer submits the registration form successfully with a shippable country', async () => {
            await StorefrontAccountLogin.shippingAddressCountryInput.selectOption({ label: shippableCountry.name });
            await ShopCustomer.expects(StorefrontAccountLogin.shippingAddressStateInput).toBeVisible();
            await StorefrontAccountLogin.shippingAddressFirstNameInput.fill(defaultRegistrationData.firstName);
            await StorefrontAccountLogin.shippingAddressLastNameInput.fill(defaultRegistrationData.lastName);
            await StorefrontAccountLogin.shippingAddressStreetAddressInput.fill(defaultRegistrationData.street);
            await StorefrontAccountLogin.shippingAddressPostalCodeInput.fill(defaultRegistrationData.postalCode);
            await StorefrontAccountLogin.shippingAddressCityInput.fill(defaultRegistrationData.city);
            await StorefrontAccountLogin.registerButton.click();

            await ShopCustomer.expects(StorefrontAccount.headline).toBeVisible();
            await deleteRegisteredUser(AdminApiContext, customer.email);
        });
    }
);

test(
    'Customers is not able to set new shipping address with a non-shippable country.',
    { tag: '@Account @Address' },
    async ({
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
        const nonShippableCountry = await TestDataService.createCountry({
            shippingAvailable: false,
            states: [
                {
                    name: 'California',
                    shortCode: 'CA',
                },
            ],
        });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, nonShippableCountry.id);
        const shippableCountry = await TestDataService.createCountry({
            states: [
                {
                    name: 'California',
                    shortCode: 'CA',
                },
            ],
        });
        await TestDataService.assignSalesChannelCountry(DefaultSalesChannel.salesChannel.id, shippableCountry.id);

        const defaultRegistrationData: RegistrationData = {
            isCommercial: false,
            isGuest: false,
            salutation: 'Mr.',
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: `${IdProvider.getIdPair().uuid}@test.com`,
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: `${nonShippableCountry.name} (Delivery not possible)`,
            postalCode: '48624',
            company: 'shopware',
            department: 'Operations',
            vatRegNo: 'DE1234567890',
        };

        const address = {
            firstName: 'New First Name',
            lastName: 'New Last Name',
            company: defaultRegistrationData.company,
            department: defaultRegistrationData.department,
            street: defaultRegistrationData.street,
            zipCode: defaultRegistrationData.postalCode,
            city: defaultRegistrationData.city,
            country: nonShippableCountry.name,
        };

        const customer = { email: defaultRegistrationData.email, password: defaultRegistrationData.password };

        await test.step('Customer select non-shippable country during registration', async () => {
            await ShopCustomer.goesTo(StorefrontAccountLogin.url());
            await ShopCustomer.attemptsTo(Register(defaultRegistrationData));
            await ShopCustomer.expects(StorefrontAccount.cannotDeliverToCountryAlert).toBeVisible();
        });

        await test.step('Customer cannot set address with non-shippable country as new shipping address', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.attemptsTo(Login(customer));
            await ShopCustomer.expects(StorefrontAccount.cannotDeliverToCountryAlert).toBeVisible();
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
        });

        await test.step('Customer cannot set non-shippable country as new shipping address', async () => {
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7')) {
                await ShopCustomer.expects(StorefrontAccountAddresses.useDefaultBillingAddressButton).toBeEnabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.useDefaultShippingAddressButton).toBeDisabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.deliveryNotPossibleAlert).toBeVisible();
            } else {
                await StorefrontAccountAddresses.addressDropdownButton.click();
                await ShopCustomer.expects(StorefrontAccountAddresses.availableAddressesUseAsBillingAddress).toBeEnabled();
                await ShopCustomer.expects(StorefrontAccountAddresses.availableAddressesUseAsShippingAddress).toBeDisabled();
            }

        });
    }
);
