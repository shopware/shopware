import { test as base } from '@playwright/test';
import { statSync } from 'node:fs';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';

/**
 * Downloads a product document from the storefront and checks the file. The assertion lives here
 * because a Task returns void (same pattern as DownloadDigitalProductFromOrder).
 */
export interface DownloadDocumentOptions {
    expectedExtension?: string;
    /** Exact byte size of the file, to catch a language serving another language's (identical-looking) file. */
    expectedSizeBytes?: number;
}

export const DownloadDocument = base.extend<{ DownloadDocument: Task }, FixtureTypes>({
    DownloadDocument: async ({ ShopCustomer, StorefrontProductDetail }, use) => {
        const task = (documentName: string, options: DownloadDocumentOptions = {}) => {
            const expectedExtension = options.expectedExtension ?? 'pdf';
            return async function DownloadDocument() {
                const [download] = await Promise.all([
                    StorefrontProductDetail.page.waitForEvent('download'),
                    ShopCustomer.presses(StorefrontProductDetail.documentLink(documentName)),
                ]);

                ShopCustomer.expects(download.suggestedFilename()).toContain(`.${expectedExtension}`);
                const filePath = await download.path();
                ShopCustomer.expects(filePath).not.toBeNull();
                const size = statSync(String(filePath)).size;
                ShopCustomer.expects(size).toBeGreaterThan(0);
                if (options.expectedSizeBytes !== undefined) {
                    ShopCustomer.expects(size).toBe(options.expectedSizeBytes);
                }
            };
        };

        await use(task);
    },
});
