import { test, expect } from '@fixtures/AcceptanceTest';
import {replaceElements, setViewport} from '@shopware-ag/acceptance-test-suite';

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
        await expect(AdminYourProfile.contentView).toHaveScreenshot('Your-Profile-General-Tab.png');
    });

    await test.step('Creates a screenshot of the search preferences tab.', async () => {
        await AdminYourProfile.searchPreferencesTab.click();
        await setViewport(AdminYourProfile.page, {
            waitForSelector: AdminYourProfile.deselectAllButton,
        });
        await expect(AdminYourProfile.contentView).toHaveScreenshot('Your-Profile-Search-Preferences-Tab.png');
    });
});
