import { test, expect } from '@fixtures/AcceptanceTest';

test('Install a new Shopware instance.', { tag: '@Install' }, async ({ ShopAdmin, SetupInstall }) => {
    test.slow(); 

    await test.step('Open welcome screen and set installation wizard language to English (US)', async () => {
        await ShopAdmin.goesTo(SetupInstall.url());
        await ShopAdmin.expects(SetupInstall.welcomeHeading).toBeVisible();
        
        await SetupInstall.languageDropdown.selectOption('English (US)');
        await expect(SetupInstall.languageDropdown).toContainText('English (US)');
        
        await SetupInstall.welcomeNextLink.click();
    });

    await test.step('Check system requirements are met', async () => {
        await expect(SetupInstall.systemRequirementsHeading).toBeVisible();
        await expect(SetupInstall.systemReadyHeading).toBeVisible();
        await SetupInstall.systemNextButton.click();
    });
    
    await test.step('Agree to the license terms and conditions', async () => {
        await expect(SetupInstall.licenseHeading).toBeVisible();
        
        await SetupInstall.termsCheckbox.click();
        await expect(SetupInstall.termsCheckbox).toBeChecked();
        
        await SetupInstall.licenseNextButton.click();
    });

    await test.step('Database Configuration', async () => {
        await expect(SetupInstall.databaseHeading).toBeVisible();
        await SetupInstall.serverField.fill(process.env.ATS_DATABASE_HOST ?? 'database');
        await SetupInstall.userField.fill(process.env.ATS_DATABASE_USERNAME ?? 'root');
        await SetupInstall.passwordField.fill(process.env.ATS_DATABASE_PASSWORD ?? 'app');

        await SetupInstall.newDatabaseCheckbox.click();
        await expect(SetupInstall.newDatabaseCheckbox).toBeChecked();
        await SetupInstall.databaseNameField.fill(process.env.ATS_DATABASE_NAME ?? 'install_test');
    });

    await test.step('Install Shopware 6', async () => {
        await SetupInstall.startInstallationButton.click();
        await expect(SetupInstall.installationHeading).toBeVisible();
        
        await SetupInstall.waitForInstallationComplete();
        await expect(SetupInstall.installationCompleteHeading).toBeVisible();
        await SetupInstall.installationNextLink.click();
    });

    await test.step('Configure basic shop settings and create the first admin user', async () => {
        await expect(SetupInstall.configurationHeading).toBeVisible();

        await SetupInstall.shopNameField.fill('Basic install test');
        await SetupInstall.shopEmailField.fill('mustermann@example.com');

        // Languages, currency, country are autoselected based on installation wizard language
        await expect(SetupInstall.defaultLanguageField).toContainText('English (United States of America)');
        await expect(SetupInstall.defaultCurrencyField).toContainText('Dollar (US)');
        await expect(SetupInstall.defaultCountryField).toContainText('United States of America');
        await expect(SetupInstall.englishLanguageCheckbox).toBeChecked();
        await expect(SetupInstall.englishLanguageCheckbox).toBeDisabled();
        await expect(SetupInstall.dollarCurrencyCheckbox).toBeChecked();

        await SetupInstall.adminEmailField.fill('admin@example.com');
        await SetupInstall.adminFirstNameField.fill('Admin');
        await SetupInstall.adminLastNameField.fill('Admin');
        await SetupInstall.adminLoginField.fill('admin');
        await SetupInstall.adminPasswordField.fill('shopware');

        await SetupInstall.configurationNextButton.click();
    });

    await test.step('Download and install additional languages', async () => {
        await expect(SetupInstall.languagesDownloadHeading).toBeVisible();
    });
 
    await test.step('Finish installation and open the admin dashboard', async () => {
        await SetupInstall.waitForFinishPage();
        await expect(SetupInstall.installationCompletedHeading).toBeVisible();
        await SetupInstall.continueToShopLink.click();

        await SetupInstall.waitForAdminDashboard();
        await expect(SetupInstall.dashboardHeading).toBeVisible({ timeout: 20000 });
    });

    await test.step('Check that the system language is available', async () => {
        await SetupInstall.gotoAdminLanguages();
        await expect(SetupInstall.settingsLanguagesHeading).toBeVisible();
        await expect(SetupInstall.languagesCount).toBeVisible();
        await expect(SetupInstall.englishDefaultRow).toBeVisible();
    });

    await test.step('Check that the system language snippets are available', async () => {
        await SetupInstall.gotoAdminSnippets();
        await expect(SetupInstall.settingsSnippetsHeading).toBeVisible();
        await expect(SetupInstall.baseEnUsRow).toBeVisible(); 
    });
});
