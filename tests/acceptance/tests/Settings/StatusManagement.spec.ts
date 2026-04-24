import { test } from '@fixtures/AcceptanceTest';

test(
    'As an admin, I can find status management by using the settings search.',
    { tag: '@Settings' },
    async ({ ShopAdmin, AdminSettingsListing, AdminStatusManagement, SearchShopSettings, Translate }) => {
        await ShopAdmin.attemptsTo(SearchShopSettings(Translate('administration:settings:links.statusManagement')));

        await ShopAdmin.expects(AdminSettingsListing.statusManagementLink).toBeVisible();
        await ShopAdmin.expects(
            AdminSettingsListing.getGroupTitleForLink(AdminSettingsListing.statusManagementLink)
        ).toContainText(Translate('administration:settings:groups.general'));

        await AdminSettingsListing.statusManagementLink.click();

        await ShopAdmin.expects(AdminStatusManagement.header).toBeVisible();
        await ShopAdmin.expects(AdminStatusManagement.stateMachineGrid).toBeVisible();
    }
);
