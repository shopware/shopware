import type { Page } from '@playwright/test';

export interface PageObject {
    readonly page: Page;

    goTo(): void;
}
