import { test } from '@fixtures/AcceptanceTest';

test('Journey: Merchant is able to be guided through the First Run Wizard.', async ({
    shopAdmin,
    firstRunWizardPage,
    defaultStorefront,
    FRWSalesChannelSelectionPossibility,
}) => {
    await shopAdmin.goesTo(firstRunWizardPage);

    //LanguagePack part
    await shopAdmin.expects(firstRunWizardPage.installLanguagePackButton).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.welcomeText).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.pluginCardInfo).toBeVisible();
    await firstRunWizardPage.nextButton.click();

    //DataImport part
    await shopAdmin.expects(firstRunWizardPage.dataImportHeader).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.dataImportCard).toHaveCount(2);
    await shopAdmin.expects(firstRunWizardPage.installDemoDataButton).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.installMigrationAssistantButton).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.backButton).not.toBeVisible();
    await firstRunWizardPage.nextButton.click();

    //Setup default values part
    await shopAdmin.expects(firstRunWizardPage.defaultValuesHeader).toBeVisible();
    const currentSalesChannel = defaultStorefront.salesChannel.name;
    await shopAdmin.attemptsTo(FRWSalesChannelSelectionPossibility(currentSalesChannel));
    await firstRunWizardPage.nextButton.click();

    //Mailer configuration part
    await shopAdmin.expects(firstRunWizardPage.mailerConfigurationHeader).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.nextButton).toBeDisabled();
    await firstRunWizardPage.smtpServerButton.click();
    await firstRunWizardPage.nextButton.click();
    await shopAdmin.expects(firstRunWizardPage.smtpServerTitle).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.smtpServerFields).toHaveCount(8);
    await firstRunWizardPage.configureLaterButton.click();

    //PayPal setup part
    await shopAdmin.expects(firstRunWizardPage.payPalSetupHeader).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.payPalInfoCard).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.payPalPaymethods).toHaveCount(4);
    await firstRunWizardPage.skipButton.click();

    //Extensions part
    await shopAdmin.expects(firstRunWizardPage.extensionsHeader).toBeVisible();
    await firstRunWizardPage.germanRegionSelector.click();
    await firstRunWizardPage.toolsSelector.click();
    await shopAdmin.expects(firstRunWizardPage.toolsRecommendedPlugin).toContainText('Migration Assistant');
    await shopAdmin.expects(firstRunWizardPage.recommendationHeader).toBeVisible()
    await firstRunWizardPage.nextButton.click();

    //Shopware account part
    await shopAdmin.expects(firstRunWizardPage.shopwareAccountHeader).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.emailAddressInputField).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.passwordInputField).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.forgotPasswordLink).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.nextButton).toBeVisible();
    await firstRunWizardPage.skipButton.click();

    //Shopware store part
    await shopAdmin.expects(firstRunWizardPage.shopwareStoreHeader).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.extensionStoreHeading).toBeVisible();
    await firstRunWizardPage.skipButton.click();
    await shopAdmin.expects(firstRunWizardPage.frwSuccessText).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.documentationLink).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.forumLink).toBeVisible();
    await shopAdmin.expects(firstRunWizardPage.roadmapLink).toBeVisible();
    await firstRunWizardPage.finishButton.click();

});
