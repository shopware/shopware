import { test, assertScreenshot, setViewport } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('Visual: Administration settings privacy page.', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDataSharing,
    InstanceMeta,
}) => {

    await test.step('Creates a screenshot of privacy page.', async () => {
        await ShopAdmin.goesTo(AdminDataSharing.url());
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7.9.0')) {
            await setViewport(AdminDataSharing.page, {
                waitForSelector: AdminDataSharing.dataSharingAgreeButton,
            });
        } else {
            await setViewport(AdminDataSharing.page, {
                waitForSelector: AdminDataSharing.dataSharingStoreDataCheckbox,
            });
        }
        await assertScreenshot(AdminDataSharing.page, 'Privacy.png');
    });
});
