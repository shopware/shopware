import { test as base } from '@playwright/test';
import { components } from '@shopware/api-client/admin-api-types';
import { expect } from '@fixtures/AcceptanceTest';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const Register = base.extend<{ Register: Task }, FixtureTypes>({
    Register: async ({ accountLoginPage, adminApiContext }, use) => {

        const registrationData = {
            firstName: 'Jeff',
            lastName: 'Goldblum',
            email: 'invalid',
            password: 'shopware',
            street: 'Ebbinghof 10',
            city: 'Schöppingen',
            country: 'Germany',
        }
        
        const task = (email: string) => {
            return async function Register() {

                registrationData.email = email;

                await accountLoginPage.firstNameInput.fill(registrationData.firstName);
                await accountLoginPage.lastNameInput.fill(registrationData.lastName);

                await accountLoginPage.registerEmailInput.fill(registrationData.email);

                await accountLoginPage.registerPasswordInput.fill(registrationData.password);
                await accountLoginPage.streetAddressInput.fill(registrationData.street);
                await accountLoginPage.cityInput.fill(registrationData.city);
                await accountLoginPage.countryInput.selectOption(registrationData.country);

                await accountLoginPage.registerButton.click();
            }
        };

        await use(task);

        const customerResponse = await adminApiContext.post('./search/customer', {
            data: {
                limit: 1,
                filter: [
                    {
                        type: 'equals',
                        field: 'email',
                        value: registrationData.email,
                    },
                ],
            },
        });

        expect(customerResponse.ok()).toBe(true);
        const customerResponseData = await customerResponse.json() as { data: components['schemas']['Customer'][] };

        for (const customer of customerResponseData.data) {
            await adminApiContext.delete(`customer/${customer.id}`);
        }

    },
});
