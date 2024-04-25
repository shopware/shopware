import { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class FirstRunWizardPage implements PageObject {

    public readonly nextButton: Locator;
    public readonly configureLaterButton: Locator;
    public readonly skipButton: Locator;
    public readonly finishButton: Locator;
    public readonly backButton: Locator;
    public readonly smtpServerButton: Locator;
    public readonly dataImportHeader: Locator;
    public readonly installLanguagePackButton: Locator;
    public readonly installDemoDataButton: Locator;
    public readonly installMigrationAssistantButton: Locator;
    public readonly defaultValuesHeader: Locator;
    public readonly mailerConfigurationHeader: Locator;
    public readonly payPalSetupHeader: Locator;
    public readonly extensionsHeader: Locator;
    public readonly shopwareAccountHeader: Locator;
    public readonly shopwareStoreHeader: Locator;
    public readonly doneHeader: Locator;
    public readonly frwSuccessText:Locator;
    public readonly welcomeText: Locator;
    public readonly pluginCardInfo: Locator;
    public readonly dataImportCard: Locator;
    public readonly salesChannelSelectionList: Locator;
    public readonly salesChannelSelectionMultiSelect: Locator;
    public readonly smtpServerTitle: Locator;
    public readonly smtpServerFields: Locator;
    public readonly payPalPaymethods: Locator;
    public readonly payPalInfoCard: Locator;
    public readonly emailAddressInputField: Locator;
    public readonly passwordInputField: Locator;
    public readonly forgotPasswordLink: Locator;
    public readonly extensionStoreHeading: Locator;
    public readonly documentationLink: Locator;
    public readonly forumLink: Locator;
    public readonly roadmapLink: Locator;
    public readonly germanRegionSelector: Locator;
    public readonly toolsSelector: Locator;
    public readonly recommendationHeader: Locator;
    public readonly toolsRecommendedPlugin: Locator;

    constructor(public readonly page: Page) {

        // Generic buttons
        this.nextButton = page.getByText('Next', { exact: true });
        this.configureLaterButton = page.getByText('Configure later', { exact: true });
        this.skipButton = page.getByText('Skip', { exact: true });
        this.finishButton = page.getByText('Finish', { exact: true });
        this.backButton = page.getByText('Back', { exact: true });

        // LanguagePack part
        this.installLanguagePackButton = page.getByRole('button', { name: 'Install' });
        this.welcomeText = page.locator('.headline-welcome', { hasText: 'Welcome to the Shopware 6 Administration' });
        this.pluginCardInfo = page.locator('.sw-plugin-card__info');

        // Data import part
        this.installMigrationAssistantButton = page.getByRole('button', { name: 'Install Migration Assistant' });
        this.installDemoDataButton = page.getByRole('button', { name: 'Install demo data' });
        this.dataImportHeader = page.locator('.sw-modal__title', { hasText: 'Getting started with Shopware 6'});
        this.dataImportCard = page.locator('.sw-first-run-wizard-data-import__card');

        // Default values part
        this.defaultValuesHeader = page.locator('.sw-modal__title', { hasText: 'Setup default values'});
        this.salesChannelSelectionMultiSelect = page.getByPlaceholder('Select Sales Channels...');
        this.salesChannelSelectionList = page.locator('.sw-popover__wrapper').getByRole('listitem');

        // Mailer configuration part
        this.mailerConfigurationHeader = page.locator('.sw-modal__title', { hasText: 'Mailer configuration'});
        this.smtpServerButton = page.getByText('Configure own SMTP server', { exact: true });
        this.smtpServerTitle = page.getByText('SMTP server', { exact: true });
        this.smtpServerFields = page.locator('.sw-field');

        // PayPal part
        this.payPalSetupHeader = page.locator('.sw-modal__title', { hasText: 'Setup PayPal'});
        this.payPalPaymethods = page.locator('.paymethod');
        this.payPalInfoCard = page.locator('.sw-first-run-wizard-paypal-info');

        // Extensions part
        this.extensionsHeader = page.locator('.sw-modal__title', { hasText: 'Extensions'});
        this.germanRegionSelector = page.getByText('Germany / Austria / Switzerland');
        this.toolsSelector = page.getByText('Tools');
        this.recommendationHeader = page.getByText('Global recommendations', { exact: true });
        this.toolsRecommendedPlugin = page.locator('.sw-plugin-card__info').locator('.sw-plugin-card__label');

        // Shopware account part
        this.shopwareAccountHeader = page.locator('.sw-modal__title', { hasText: 'Shopware Account'});
        this.emailAddressInputField = page.getByPlaceholder('Enter your email address...', { exact: true });
        this.passwordInputField = page.getByPlaceholder('Enter your password...', { exact: true });
        this.forgotPasswordLink = page.getByText('Did you forget your password?', { exact: true })

        // Shopware store part
        this.shopwareStoreHeader = page.locator('.sw-modal__title', { hasText: 'Shopware Store'});
        this.doneHeader = page.locator('.sw-modal__title', { hasText: 'Done'});
        this.extensionStoreHeading = page.locator('.sw-first-run-wizard-store__heading');
        this.frwSuccessText = page.getByText('All done!', { exact: true });
        this.documentationLink = page.locator('[href*="https://docs.shopware.com/en"]');
        this.forumLink = page.locator('[href*="https://forum.shopware.com/"]');
        this.roadmapLink = page.locator('[href*="https://www.shopware.com/en/roadmap/"]');

    }
    async goTo() {
        await this.page.goto(`#/sw/first/run/wizard/index/`);
    }
}
