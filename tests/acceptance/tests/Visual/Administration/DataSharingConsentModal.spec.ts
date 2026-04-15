import { test, assertScreenshot, setViewport, Page} from '@fixtures/AcceptanceTest';
import {
    removeSymfonyToolbar, setupConsentInterceptor, setupProductAnalyticsInterceptor,
} from '@helpers/productanalytics-helpers';
import { AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@shopware-ag/acceptance-test-suite';
import { satisfies } from 'compare-versions';

const PRODUCT_ANALYTICS_ENDPOINT = 'event';
const CONSENTS_ENDPOINT = 'consents';

test('Visual: Administration data sharing consent modal', { tag: '@Visual' }, async ({
    TestDataService,
    browser,
    SalesChannelBaseConfig,
    InstanceMeta,
}) => {

    test.skip(satisfies(InstanceMeta.version, '>=6.7.9.0'), 'Data sharing consent modal only available since version 6.7.9.0');

    const { handler } = setupProductAnalyticsInterceptor();
    const { consentHandler } = setupConsentInterceptor();

    const page: Page = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
    const user: User = await TestDataService.createUser({ createdAt: '2024-01-01T00:00:00.000Z' });
    const AdminDataSharingConsentModal = new AdminPageObjects['DataSharingConsentModal'](page);

    await test.step('Modify product analytics API and consent API requests.', async () => {

        await page.route(`**/${PRODUCT_ANALYTICS_ENDPOINT}**`, handler);
        await page.route(`**/${CONSENTS_ENDPOINT}`, consentHandler);
    });

    await test.step('Login to shopware administration.', async () => {

        await loginToAdministration(
            page,
            user,
            TestDataService.AdminApiClient,
        );
        await removeSymfonyToolbar(page);
    });

    await test.step('Creates a screenshot of data sharing consent modal.', async () => {

        await setViewport(AdminDataSharingConsentModal.page, {
            waitForSelector: AdminDataSharingConsentModal.shareStoreDataCheckbox,
        });
        await assertScreenshot(AdminDataSharingConsentModal.page, 'Modal-Data-Sharing-Consent.png');
    });
});
