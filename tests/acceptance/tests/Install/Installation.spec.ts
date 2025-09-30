import { test, expect } from '@fixtures/AcceptanceTest';

test('Install a new Shopware instance.', { tag: '@Install' }, async ({ IdProvider, InstallPage }) => {

    const page = InstallPage;

    test.slow(); 

    await test.step('Open welcome screen and set installation wizard language to English (US)', async () => {
        await page.goto(process.env.APP_URL);
        await expect(page.getByRole('heading', { name: 'Welcome to Shopware 6',  level: 1 } )).toBeVisible();
        
        const installerLanguageDropdown = page.getByLabel('Installation wizard language');
        await installerLanguageDropdown.selectOption('English (US)');
        await expect(installerLanguageDropdown).toContainText('English (US)');
        
        await page.getByRole('link', { name: 'Next' }).click();
     });

    await test.step('Check system requirements are met', async () => {
        await expect(page.getByRole('heading', { name: 'System requirements', level: 2 })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Your system is ready for Shopware 6', level: 3 })).toBeVisible();
        await page.getByRole('button', { name: 'Next' }).click();
    });
    
    await test.step('Agree to the license terms and conditions', async () => {
        await expect(page.getByRole('heading', { name: 'General Terms and Conditions of Business ("GTC")', level: 2 })).toBeVisible();
        
        const termsCheckbox = page.getByText('I agree to the General Terms and Conditions of Business (GTC)');
        await termsCheckbox.click();
        await expect(termsCheckbox).toBeChecked();
        
        await page.getByRole('button', { name: 'Next' }).click();
    });

    await test.step('Database Configuration', async () => {
        await expect(page.getByRole('heading', { name: 'Configure database', level: 2 })).toBeVisible();
        await page.getByLabel('Server:').fill(process.env.ATS_DATABASE_HOST ?? 'database');
        await page.getByLabel('User:').fill(process.env.ATS_DATABASE_USERNAME ?? 'root');
        await page.getByLabel('Password:').fill(process.env.ATS_DATABASE_PASSWORD ?? 'app');

        const newDatabaseCheckbox = page.getByText('New database:');
        await newDatabaseCheckbox.click();
        await expect(newDatabaseCheckbox).toBeChecked();
        await page.locator('#databaseName_new').fill(process.env.ATS_DATABASE_NAME ?? 'install_test');
    });

    await test.step('Install Shopware 6', async () => {
        const installComplete = page.waitForURL(`${process.env.APP_URL}installer/database-import`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Start installation' }).click();
        await expect(page.getByRole('heading', { name: 'Installation', level: 2 })).toBeVisible();
        
        const installed = await installComplete;
        await expect(page.getByRole('heading', { name: 'Shopware 6 has been installed!', level: 3 })).toBeVisible();
        await page.getByRole('link', { name: 'Next' }).click();
    });

    await test.step('Configure basic shop settings and create the first admin user', async () => {
        await expect(page.getByRole('heading', { name: 'Configuration', level: 2 })).toBeVisible();

        await page.getByLabel('Shop name').fill('Basic install test');
        await page.getByLabel('Shop email address:').fill('mustermann@example.com');

        //languages, currency, country are autoselected based on installation wizard language
        await expect(page.getByLabel('Default system language:')).toContainText('English (United States of America)');
        await expect(page.getByLabel('Default currency:')).toContainText('Dollar (US)');
        await expect(page.getByLabel('Default country:')).toContainText('United States of America');
        await expect(page.getByRole('checkbox', { name: 'English (US)' })).toBeChecked();
        await expect(page.getByRole('checkbox', { name: 'English (US)' })).toBeDisabled();
        await expect(page.getByRole('checkbox', { name: 'Dollar (US)' })).toBeChecked();

        await page.getByLabel('Admin email:').fill('admin@example.com');
        await page.getByLabel('Admin first name:').fill('Admin');
        await page.getByLabel('Admin last name:').fill('Admin');
        await page.getByLabel('Admin login name:').fill('admin');
        await page.getByLabel('Admin password:').fill('shopware');

        await page.getByRole('button', { name: 'Next' }).click();
    });

    await test.step('Download and install additional languages', async () => {
        await expect(page.getByRole('heading', { name: 'Languages Download', level: 2 })).toBeVisible();
    });
 
    await test.step('Finish installation and open the admin dashboard', async () => {
        await page.waitForURL(`${process.env.APP_URL}installer/finish?completed=1`, { waitUntil: 'commit' });
        await expect(page.getByRole('heading', { name: 'Installation completed', level: 2 })).toBeVisible();
        await page.getByRole('link', { name: 'Continue to shop' }).click();

        await page.waitForURL(`${process.env.APP_URL}admin#/sw/dashboard/index`, { waitUntil: 'commit' });
        await expect(page.getByRole('heading', { name: 'Introducing Shopware Services', level: 3 })).toBeVisible({ timeout: 20000 });
    });

    await test.step('Check that the system language is available', async () => {
        await page.goto(`${process.env.APP_URL}admin#/sw/settings/language/index`, { waitUntil: 'commit'});
        await expect(page.getByRole('heading', { name: 'Settings Languages', level: 2 })).toBeVisible();
        await expect(page.getByText('Languages (3)')).toBeVisible();
        await expect(page.getByRole('row', { name: 'English (US) Default' }).locator('#meteor-icon-kit__regular-checkmark-xs')).toBeVisible();
    });

    await test.step('Check that the system language snippets are available', async () => {
        await page.goto(`${process.env.APP_URL}admin#/sw/settings/snippet/index`, { waitUntil: 'commit'});
        await expect(page.getByRole('heading', { name: 'Settings Snippets', level: 2 })).toBeVisible();
        await expect(page.getByRole('row', { name: 'BASE en-US' })).toBeVisible(); 
    });
});
