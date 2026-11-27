import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Reorders a product document one step up or down via the Position column buttons.
 */
export const SortDocument = base.extend<{ SortDocument: Task }, FixtureTypes>({
    SortDocument: async ({ AdminProductDetail }, use) => {
        const task = (documentName: string, direction: 'up' | 'down') => {
            return async function SortDocument() {
                const button =
                    direction === 'up'
                        ? AdminProductDetail.documentMoveUpButton(documentName)
                        : AdminProductDetail.documentMoveDownButton(documentName);
                await button.click();
            };
        };

        await use(task);
    },
});
