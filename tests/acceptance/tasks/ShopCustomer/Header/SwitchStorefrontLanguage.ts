import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { getLanguageData } from '@fixtures/AcceptanceTest';

/**
 * Switches the storefront language via the header language dropdown. Each option is a
 * `button[name="languageId"]` that submits the language form and navigates to that language's
 * sales-channel domain. The option is selected by languageId, not its label: 6.8 renders the name
 * as the locale ("German (Germany)") rather than the stored language name ("Deutsch"), so a
 * text match would be version-dependent. The switch is confirmed via the document `lang` attribute,
 * which reflects the active locale regardless of the displayed name. The sales channel must have the
 * target language and a domain for the switcher to be present.
 */
export const SwitchStorefrontLanguage = base.extend<{ SwitchStorefrontLanguage: Task }, FixtureTypes>({
    SwitchStorefrontLanguage: async ({ ShopCustomer, StorefrontHeader, TestDataService }, use) => {
        const task = (locale: string) => {
            return async function SwitchStorefrontLanguage() {
                const language = await getLanguageData(TestDataService.AdminApiClient, locale);
                await ShopCustomer.presses(StorefrontHeader.languagesDropdown);
                await ShopCustomer.presses(
                    StorefrontHeader.languagesMenuOptions.locator(`button[name="languageId"][value="${language.id}"]`),
                );
                await ShopCustomer.expects(StorefrontHeader.page.locator('html')).toHaveAttribute('lang', locale);
            };
        };

        await use(task);
    },
});
