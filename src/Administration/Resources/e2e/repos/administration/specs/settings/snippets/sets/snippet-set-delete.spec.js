const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-set-delete', 'snippets', 'snippet-set', 'delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('snippet-set').then(() => {
            done();
        });
    },
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-snippet')
            .assert.urlContains('#/sw/settings/snippet/index');
    },
    'verify snippet set to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.equals(global.AdminFixtureService.basicFixture.name);
    },
    'delete snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(`${page.elements.modal} ${page.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete the snippet set "${global.AdminFixtureService.basicFixture.name}"?`);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification('Snippet set has been deleted successfully.')
            .waitForElementNotPresent(page.elements.loader);
    },
    'verify deletion': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.not.equals('A Set Name Snippet');
    }
};
