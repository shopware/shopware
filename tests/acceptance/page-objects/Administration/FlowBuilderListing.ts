import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class FlowBuilderListingPage implements PageObject {

    public readonly firstFlowName: Locator;
    public readonly firstFlowContextButton: Locator;
    public readonly flowContextMenu: Locator;
    public readonly contextMenuDownload: Locator;
    public readonly flowDownloadModal: Locator;
    public readonly downloadFlowButton: Locator;
    public readonly successAlert: Locator;
    public readonly successAlertMessage: Locator;

    constructor(public readonly page: Page) {
        this.firstFlowName = page.locator('.sw-data-grid__cell--name a').first();
        this.firstFlowContextButton = page.locator('.sw-data-grid__actions-menu').first();
        this.flowContextMenu = page.locator('.sw-context-menu__content');
        this.contextMenuDownload = page.locator('.sw-flow-list__item-download');
        this.flowDownloadModal = page.locator(' .sw-flow-download-modal');
        this.downloadFlowButton = page.getByRole('button', { name: 'Download flow' });
        this.successAlert = page.locator('.sw-alert__body');
        this.successAlertMessage = page.locator('.sw-alert__message');

    }

    async goTo() {
        await this.page.goto(`#/sw/flow/index/`);
    }
}
