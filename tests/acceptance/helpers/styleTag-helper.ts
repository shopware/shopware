import { Page } from '@playwright/test';

export async function removeSymfonyToolbar(page: Page): Promise<void> {
    await page.addStyleTag({
        content: `
            .sf-toolbar {
                width: 0 !important;
                height: 0 !important;
                display: none !important;
                pointer-events: none !important;
            }
            `.trim(),
    });
}
