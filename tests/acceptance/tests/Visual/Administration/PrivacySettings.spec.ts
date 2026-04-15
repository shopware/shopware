import { test, assertScreenshot, setViewport, Page, replaceElements} from '@fixtures/AcceptanceTest';
import {
    removeSymfonyToolbar, setupConsentInterceptor, setupProductAnalyticsInterceptor,
} from '@helpers/productanalytics-helpers';
import { AdminPageObjects, createNewAdminPageContext, loginToAdministration, User } from '@shopware-ag/acceptance-test-suite';
import { satisfies } from 'compare-versions';

test('Visual: Administration your profile page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDataSharing,
    InstanceMeta,
}) => {

    // eslint-disable-next-line playwright/no-conditional-in-test
    if (satisfies(InstanceMeta.version, '>=6.7.9.0')) {
        await test.step('Creates a screenshot of privacy page.', async () => {
            await ShopAdmin.goesTo(AdminDataSharing.url());
            await setViewport(AdminDataSharing.page, {
                waitForSelector: AdminDataSharing.dataSharingStoreDataCheckbox,
            });
            await assertScreenshot(AdminDataSharing.page, 'Privacy.png');
        });
    }
});
