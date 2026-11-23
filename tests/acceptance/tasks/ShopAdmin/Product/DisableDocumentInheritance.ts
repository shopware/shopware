import { test as base } from '@playwright/test';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Unlinks the Documents card on a variant (inheritance switch on the card header) so the variant
 * can manage its own documents. While inherited, the "Add documents" button is disabled.
 */
export const DisableDocumentInheritance = base.extend<{ DisableDocumentInheritance: Task }, FixtureTypes>({
    DisableDocumentInheritance: async ({ AdminProductDetail }, use) => {
        const task = () => {
            return async function DisableDocumentInheritance() {
                await AdminProductDetail.documentsCardInheritanceSwitch.click();
            };
        };

        await use(task);
    },
});
