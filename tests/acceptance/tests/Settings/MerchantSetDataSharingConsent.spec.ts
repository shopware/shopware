import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import { Response, isSaaSInstance } from '@shopware-ag/acceptance-test-suite';

test('Journey: Merchant is able accept or decline the data sharing consent.', async ({
    ShopAdmin,
    AdminDashboard,
    AdminDataSharing,
    AdminApiContext,
}) => {
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (await isSaaSInstance(AdminApiContext)) {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip('Skipping test for merchants consent process, because it is disabled on SaaS instances.');
    }
    let consentResponsePromise: Promise<Response>;
    let response: Response;

    await test.step('Validate the initial consent state', async () => {
        await ShopAdmin.goesTo(AdminDashboard);
        await ShopAdmin.expects(AdminDashboard.dataSharingConsentBanner).toBeVisible();
        await ShopAdmin.expects(AdminDashboard.dataSharingTermsAgreementLabel).toBeVisible();
    });

    await test.step('Validate the accepting of the consent on dashboard page', async () => {
        consentResponsePromise = AdminDashboard.page.waitForResponse('**/api/usage-data/accept-consent');
        await AdminDashboard.dataSharingAgreeButton.click();
        response = await consentResponsePromise;
        expect(response.status()).toBe(204);
        await ShopAdmin.expects(AdminDashboard.dataSharingAcceptMessageText).toBeVisible();
        await ShopAdmin.expects(AdminDashboard.dataSharingSettingsLink).toBeVisible();

        await AdminDashboard.dataSharingSettingsLink.click();
        await ShopAdmin.expects(AdminDataSharing.dataSharingSuccessMessageLabel).toBeVisible();
    });

    await test.step('Validate the declining and accepting of the consent on data sharing page', async () => {
        consentResponsePromise = AdminDataSharing.page.waitForResponse('**/api/usage-data/revoke-consent');
        await AdminDataSharing.dataSharingDisableButton.click();
        response = await consentResponsePromise;
        expect(response.status()).toBe(204);
        await ShopAdmin.expects(AdminDataSharing.dataSharingTermsAgreementLabel).toBeVisible();

        consentResponsePromise = AdminDataSharing.page.waitForResponse('**/api/usage-data/accept-consent');
        await AdminDataSharing.dataSharingAgreeButton.click();
        response = await consentResponsePromise;
        expect(response.status()).toBe(204);
        await ShopAdmin.expects(AdminDataSharing.dataSharingSuccessMessageLabel).toBeVisible();

        await AdminDataSharing.dataSharingDisableButton.click();
    });

    await test.step('Validate the declining of the consent and hiding of the consent banner on dashboard page', async () => {
        await ShopAdmin.goesTo(AdminDashboard);
        await ShopAdmin.expects(AdminDashboard.dataSharingConsentBanner).toBeVisible();

        consentResponsePromise = AdminDashboard.page.waitForResponse('**/api/usage-data/hide-consent-banner');
        await AdminDashboard.dataSharingNotAtTheMomentButton.click();
        response = await consentResponsePromise;
        expect(response.status()).toBe(204);
        await ShopAdmin.expects(AdminDashboard.dataSharingNotAtTheMomentMessageText).toBeVisible();
        await ShopAdmin.expects(AdminDashboard.dataSharingSettingsLink).toBeVisible();

        await AdminDashboard.dataSharingSettingsLink.click();
        await ShopAdmin.expects(AdminDataSharing.dataSharingTermsAgreementLabel).toBeVisible();
        await ShopAdmin.expects(AdminDataSharing.dataSharingAgreeButton).toBeVisible();
        await ShopAdmin.goesTo(AdminDashboard);
        await ShopAdmin.expects(AdminDashboard.dataSharingConsentBanner).not.toBeVisible();
    });
});
