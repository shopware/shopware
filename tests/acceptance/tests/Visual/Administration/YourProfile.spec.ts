import { test, assertScreenshot, replaceElements, setViewport } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('Visual: Administration your profile page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminYourProfile,
    InstanceMeta,
}) => {

    await test.step('Creates a screenshot of the your profile page.', async () => {
        await ShopAdmin.goesTo(AdminYourProfile.url());
        await setViewport(AdminYourProfile.page, {
            waitForSelector: AdminYourProfile.emailField,
        });
        await replaceElements(AdminYourProfile.page, [
            AdminYourProfile.firstNameField,
            AdminYourProfile.lastNameField,
            AdminYourProfile.userNameField,
            AdminYourProfile.emailField,
            ]
        );
        await assertScreenshot(AdminYourProfile.page, 'Your-Profile-General-Tab.png');
    });

    await test.step('Creates a screenshot of the search preferences tab.', async () => {
        await AdminYourProfile.searchPreferencesTab.click();
        await setViewport(AdminYourProfile.page, {
            waitForSelector: AdminYourProfile.deselectAllButton,
        });
        await assertScreenshot(AdminYourProfile.page, 'Your-Profile-Search-Preferences-Tab.png');
    });

    // eslint-disable-next-line playwright/no-conditional-in-test
    if (satisfies(InstanceMeta.version, '>=6.7.9.0')) {
        await test.step('Creates a screenshot of tab privacy preferences on your profile page.', async () => {
            await ShopAdmin.goesTo(AdminYourProfile.url('privacy-preferences'));
            await setViewport(AdminYourProfile.page, {
                waitForSelector: AdminYourProfile.dataSharingUsageDataCheckbox,
            });
            await assertScreenshot(AdminYourProfile.page, 'Your-Profile-Privacy-Preferences-Tab.png');
        });
    }
});
