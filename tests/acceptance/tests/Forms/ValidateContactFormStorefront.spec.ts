import { test, expect } from '@fixtures/AcceptanceTest';

test.describe('@Storefront', {
  tag: ['@Form', '@Captcha', '@Contact'],
}, () => {

    test(
        'As a customer, I want to fill out and submit the contact form.',
        { tag: '@form @contact' },
        async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, DefaultSalesChannel }) => {

            await test.step('Open the contact form modal on home page.', async () => {
                await ShopCustomer.goesTo(StorefrontHome.url());
                await ShopCustomer.presses(StorefrontHome.contactFormLink, 'Enter');
                await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
            });

            await test.step('Fill out all necessary contact information.', async () => {
                await ShopCustomer.presses(StorefrontContactForm.salutationSelect, 'Space');
                await StorefrontContactForm.salutationSelect.selectOption('Mr.');
                await ShopCustomer.fillsIn(StorefrontContactForm.firstNameInput, 'John');
                await ShopCustomer.fillsIn(StorefrontContactForm.lastNameInput, 'Doe');
                await ShopCustomer.fillsIn(StorefrontContactForm.emailInput, 'mail@test.com');
                await ShopCustomer.fillsIn(StorefrontContactForm.phoneInput, '0123456789');
                await ShopCustomer.fillsIn(StorefrontContactForm.subjectInput, 'Test: Product question');
                await ShopCustomer.fillsIn(StorefrontContactForm.commentInput, 'Test: Hello, I have a question about your products.');
            });

            await test.step('Send and validate the contact form.', async () => {
                const contactFormPromise = StorefrontContactForm.page.waitForResponse(
                    `${process.env['APP_URL'] + 'test-' + DefaultSalesChannel.salesChannel.id}/form/contact`
                );
                await ShopCustomer.presses(StorefrontContactForm.submitButton, 'Enter');
                const contactFormResponse = await contactFormPromise;
                expect(contactFormResponse.ok()).toBeTruthy();

                await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).toHaveText(
                    'We have received your contact request and will process it as soon as possible.'
                );
            });
        });

    test(
        'As a customer, I forgot to fill out some fields and should be informed about the missing ones.',
        { tag: '@form @contact' },
        async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, InstanceMeta }) => {

            await test.step('Open the contact form modal on home page.', async () => {
                await ShopCustomer.goesTo(StorefrontHome.url());
                await ShopCustomer.presses(StorefrontHome.contactFormLink, 'Enter');
                await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');
            });

            await test.step('Send and validate the negative contact form result.', async () => {
                await StorefrontContactForm.page.waitForLoadState('networkidle');
                await ShopCustomer.presses(StorefrontContactForm.submitButton, 'Enter');
                await ShopCustomer.expects(StorefrontContactForm.cardTitle).toContainText('Contact');

                await ShopCustomer.expects(StorefrontContactForm.salutationSelect).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.firstNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.lastNameInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.emailInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.phoneInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.subjectInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.commentInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');

                // eslint-disable-next-line playwright/no-conditional-in-test
                if (InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                    await ShopCustomer.expects(StorefrontContactForm.formFieldFeedback).toHaveCount(7);
                }

                await ShopCustomer.expects(StorefrontContactForm.contactSuccessMessage).not.toBeVisible();
            });
        });
});
