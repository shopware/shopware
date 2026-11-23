import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { LANGUAGE_LABELS } from '@tasks/languageLabels';

/**
 * Switches the storefront language via the header language dropdown. The menu entries are
 * `button.dropdown-item` elements inside `li.top-bar-list-item`; selecting one navigates to that
 * language's sales-channel domain. The sales channel must have the target language + a domain for
 * the switcher to be present.
 */
export const SwitchStorefrontLanguage = base.extend<{ SwitchStorefrontLanguage: Task }, FixtureTypes>({
    SwitchStorefrontLanguage: async ({ ShopCustomer, StorefrontHeader }, use) => {
        const task = (locale: string) => {
            return async function SwitchStorefrontLanguage() {
                const label = LANGUAGE_LABELS[locale] ?? locale;
                await ShopCustomer.presses(StorefrontHeader.languagesDropdown);
                await ShopCustomer.presses(
                    StorefrontHeader.languagesMenuOptions
                        .locator('li.top-bar-list-item')
                        .filter({ hasText: label })
                        .locator('button.dropdown-item'),
                );
                // Confirm the switch landed (the toggle shows the active language) so a silent no-op
                // cannot let a caller's fallback/override assertion pass. The toggle id is stable.
                await ShopCustomer.expects(StorefrontHeader.page.locator('#languagesDropdown-top-bar')).toContainText(label);
            };
        };

        await use(task);
    },
});
