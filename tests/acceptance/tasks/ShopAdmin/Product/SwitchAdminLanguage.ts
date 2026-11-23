import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { LANGUAGE_LABELS } from '@tasks/languageLabels';

/**
 * Switches the Administration content language via the smart-bar language switch on the
 * product detail page. Extend LANGUAGE_LABELS when a scenario uses another language.
 */
export const SwitchAdminLanguage = base.extend<{ SwitchAdminLanguage: Task }, FixtureTypes>({
    SwitchAdminLanguage: async ({ AdminProductDetail }, use) => {
        const task = (locale: string) => {
            return async function SwitchAdminLanguage() {
                const label = LANGUAGE_LABELS[locale] ?? locale;
                // Searchable select: open, type to filter, click the exact match ("Deutsch" is a prefix
                // of "Deutsch (Schweiz)", so the option is matched on its full text).
                await AdminProductDetail.languageSwitch.click();
                await AdminProductDetail.languageSwitchInput.fill(label);
                await AdminProductDetail.languageSwitchOption(label).click();
            };
        };

        await use(task);
    },
});
