import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a customer, I expect to see and use a basic captcha function on the contact form.',
    { tag: ['@Storefront', '@Form', '@Captcha', '@Contact'] },
    async ({ ShopCustomer, StorefrontHome, StorefrontContactForm, TestDataService, InstanceMeta }) => {

        test.skip(InstanceMeta.isSaaS, 'SaaS just support FriendlyCaptcha');

        await TestDataService.setSystemConfig({'core.basicInformation.activeCaptchasV2': {'basicCaptcha': { 'name': 'basicCaptcha', 'isActive': true }} });

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
            await ShopCustomer.fillsIn(StorefrontContactForm.basicCaptchaInput, '1234');
        });

        await test.step('Validate the basic captcha is available.', async () => {
            await ShopCustomer.expects(StorefrontContactForm.basicCaptcha).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.basicCaptchaImage).toBeVisible();
            await ShopCustomer.expects(StorefrontContactForm.basicCaptchaRefreshButton).toBeVisible();
        });

        await ShopCustomer.expects(async () => {
            await test.step('Send and validate the unaccomplished contact form.', async () => {
                await ShopCustomer.presses(StorefrontContactForm.submitButton);

                await StorefrontContactForm.page.waitForResponse(resp => resp.url().includes('basic-captcha-validate'));
                await ShopCustomer.expects(StorefrontContactForm.basicCaptchaInput).toHaveCSS('border-color', 'rgb(194, 0, 23)');
                await ShopCustomer.expects(StorefrontContactForm.basicCaptchaInput).toHaveAccessibleDescription('Incorrect input. Please try again.');
            });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    }
);
