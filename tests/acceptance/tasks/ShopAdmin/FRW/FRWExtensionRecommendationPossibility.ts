import { test as base, expect, type Locator } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * @sw-package fundamentals@after-sales
 */
export const FRWExtensionRecommendationPossibility = base.extend<{ FRWExtensionRecommendationPossibility: Task }, FixtureTypes>({
    FRWExtensionRecommendationPossibility: async ({ AdminFirstRunWizard }, use) => {
        const task = (category: Locator, expectedPluginName: string) => {
            return async function FRWExtensionRecommendationPossibility() {
                // Selecting the category triggers the store recommendations request; wait for it to land
                // before asserting on its content, instead of racing the assertion against the network call.
                const recommendationsLoaded = AdminFirstRunWizard.page.waitForResponse(
                    (response) => response.url().includes('/_action/store/recommendations') && response.ok(),
                );
                await category.click();
                await recommendationsLoaded;
                await expect(AdminFirstRunWizard.toolsRecommendedPlugin.first()).toContainText(expectedPluginName);
            };
        };

        await use(task);
    },
});
