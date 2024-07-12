import { test } from '@fixtures/AcceptanceTest';
import { isSaaSInstance } from '@fixtures/AcceptanceTest';

test('Merchant is able to be guided through the First Run Wizard.', { tag: '@FirstRunWizard' }, async ({
    FRWSalesChannelSelectionPossibility,
    ShopAdmin,
    DefaultSalesChannel,
    AdminFirstRunWizard,
    AdminApiContext,
}) => {
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (await isSaaSInstance(AdminApiContext)) {
        // eslint-disable-next-line playwright/no-skipped-test
        test.skip(true,'Skipping test for the first run wizard, because it is disabled on SaaS instances.');
    }

    await ShopAdmin.goesTo(AdminFirstRunWizard.url());

    //LanguagePack part
    await ShopAdmin.expects(AdminFirstRunWizard.installLanguagePackButton).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.welcomeText).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.pluginCardInfo).toBeVisible();
    await AdminFirstRunWizard.nextButton.click();

    //DataImport part
    await ShopAdmin.expects(AdminFirstRunWizard.dataImportHeader).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.dataImportCard).toHaveCount(2);
    await ShopAdmin.expects(AdminFirstRunWizard.installDemoDataButton).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.installMigrationAssistantButton).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.backButton).not.toBeVisible();
    await AdminFirstRunWizard.nextButton.click();

    //Setup default values part
    await ShopAdmin.expects(AdminFirstRunWizard.defaultValuesHeader).toBeVisible();
    const currentSalesChannel = DefaultSalesChannel.salesChannel.name;
    await ShopAdmin.attemptsTo(FRWSalesChannelSelectionPossibility(currentSalesChannel));
    await AdminFirstRunWizard.nextButton.click();

    //Mailer configuration part
    await ShopAdmin.expects(AdminFirstRunWizard.mailerConfigurationHeader).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.nextButton).toBeDisabled();
    await AdminFirstRunWizard.smtpServerButton.click();
    await AdminFirstRunWizard.nextButton.click();
    await ShopAdmin.expects(AdminFirstRunWizard.smtpServerTitle).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.smtpServerFields).toHaveCount(8);
    await AdminFirstRunWizard.configureLaterButton.click();

    //PayPal setup part
    await ShopAdmin.expects(AdminFirstRunWizard.payPalSetupHeader).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.payPalInfoCard).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.payPalPaymethods).toHaveCount(4);
    await AdminFirstRunWizard.skipButton.click();

    //Extensions part
    await ShopAdmin.expects(AdminFirstRunWizard.extensionsHeader).toBeVisible();
    await AdminFirstRunWizard.germanRegionSelector.click();
    await AdminFirstRunWizard.toolsSelector.click();
    await ShopAdmin.expects(AdminFirstRunWizard.toolsRecommendedPlugin).toContainText('Migration Assistant');
    await ShopAdmin.expects(AdminFirstRunWizard.recommendationHeader).toBeVisible()
    await AdminFirstRunWizard.nextButton.click();

    //Shopware account part
    await ShopAdmin.expects(AdminFirstRunWizard.shopwareAccountHeader).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.emailAddressInputField).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.passwordInputField).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.forgotPasswordLink).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.nextButton).toBeVisible();
    await AdminFirstRunWizard.skipButton.click();

    //Shopware store part
    await ShopAdmin.expects(AdminFirstRunWizard.shopwareStoreHeader).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.extensionStoreHeading).toBeVisible();
    await AdminFirstRunWizard.skipButton.click();
    await ShopAdmin.expects(AdminFirstRunWizard.frwSuccessText).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.documentationLink).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.forumLink).toBeVisible();
    await ShopAdmin.expects(AdminFirstRunWizard.roadmapLink).toBeVisible();
    await AdminFirstRunWizard.finishButton.click();
});
