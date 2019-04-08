const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-delete', 'snippets', 'delete'],
    before: (browser, done) => {
        global.SnippetFixtureService.setSnippetFixtures().then(() => {
            done();
        });
    },
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/snippet/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-snippet'
            });
    },
    'open snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.not.be.enabled;
        browser
            .tickCheckbox(`${page.elements.gridRow}--1 .sw-field__checkbox input`, true)
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.be.enabled;

        browser
            .click('.sw-settings-snippet-set-list__edit-set-action')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Snippets of "BASE en_GB"');
    },
    'verify snippet to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element(`${page.elements.gridRow}--0 .sw-settings-snippet-list__column-name`).to.have.text.that.equals(global.SnippetFixtureService.snippetFixture.translationKey);
    },
    'delete snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.contains(`Are you sure you want to delete the snippets for "${global.SnippetFixtureService.snippetFixture.translationKey}"?`);

        browser
            .click(`${page.elements.modalFooter} button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .checkNotification(`Snippet "${global.SnippetFixtureService.snippetFixture.value}" has been reset to "${global.SnippetFixtureService.snippetFixture.translationKey}" successfully.`);
    },
    'verify deletion of snippet': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.not.contains(global.SnippetFixtureService.snippetFixture.translationKey);
    }
};
