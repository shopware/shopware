import { expect, test } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I want to fill out and submit the contact form.',
    { tag: ['@Form', '@Contact', '@Storefront'] },
    async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, DefaultSalesChannel }) => {

        test.slow(); //Necessary for multiple retries due to rate limiting

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.presses(StorefrontHome.contactFormLink);
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Fill out all necessary contact information.', async () => {
            await ShopCustomer.presses(StorefrontContactForm.salutationSelect);
            await StorefrontContactForm.salutationSelect.selectOption('Mr.');
            await ShopCustomer.fillsIn(StorefrontContactForm.firstNameInput, 'John');
            await ShopCustomer.fillsIn(StorefrontContactForm.lastNameInput, 'Doe');
            await ShopCustomer.fillsIn(StorefrontContactForm.emailInput, 'mail@test.com');
            await ShopCustomer.fillsIn(StorefrontContactForm.phoneInput, '0123456789');
            await ShopCustomer.fillsIn(StorefrontContactForm.subjectInput, 'Test: Product question');
            await ShopCustomer.fillsIn(StorefrontContactForm.commentInput, 'Test: Hello, I have a question about your products.');
        });

        await ShopCustomer.expects(async () => {
            await test.step('Send and validate the contact form.', async () => {

                const contactFormPromise = StorefrontContactForm.page.waitForResponse(
                    `${process.env.APP_URL}test-${DefaultSalesChannel.salesChannel.id}/form/contact`
                );
                await ShopCustomer.presses(StorefrontContactForm.submitButton);
                const contactFormResponse = await contactFormPromise;
                expect(contactFormResponse.status()).toBe(200);

                await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).toBeVisible();
            });
        }).toPass({
            intervals: [30_000], // retry after 30 seconds
        });
    }
);

test(
    'As a customer, I forgot to fill out some fields and should be informed about the missing ones.',
    { tag: ['@Form', '@Contact', '@Storefront'] },
    async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, InstanceMeta }) => {

        await test.step('Open the contact form modal on home page.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.presses(StorefrontHome.contactFormLink);
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
        });

        await test.step('Send and validate the negative contact form result.', async () => {
            await ShopCustomer.presses(StorefrontContactForm.submitButton);
            await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');

            await ShopCustomer.expects(StorefrontContactForm.salutationSelect).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.firstNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.lastNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.emailInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.phoneInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.subjectInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
            await ShopCustomer.expects(StorefrontContactForm.commentInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

            // eslint-disable-next-line playwright/no-conditional-in-test
            if (InstanceMeta.features.ACCESSIBILITY_TWEAKS) {
                await ShopCustomer.expects(StorefrontContactForm.formFieldFeedback).toHaveCount(7);
            }

            await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).not.toBeVisible();
        });
    }
);
