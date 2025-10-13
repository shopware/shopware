import { test, assertScreenshot, replaceElements, setViewport } from '@fixtures/AcceptanceTest';

test('Visual: Administration your profile page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminYourProfile,
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
});
