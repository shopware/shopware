const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-set-delete', 'snippets', 'snippet-set', 'delete'],
    '@disabled': !global.flags.isActive('next717'),
    before: (browser, done) => {
        global.AdminFixtureService.create('snippet-set').then(() => {
            done();
        });
    },
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/snippets/index',
                subMenuTitle: 'Snippets'
            });
    },
    'verify snippet set to be edited': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.equals('A Set Name Snippet');
    },
    'delete snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton)
            .expect.element(`${page.elements.modal} ${page.elements.modal}__body`).to.have.text.that.contains(`Are you sure you want to delete the snippet set "${global.FixtureService.basicFixture.name}"?`).before(browser.globals.waitForConditionTimeout);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification('Snippet set has been deleteced successfully.')
            .waitForElementNotPresent(page.elements.loader);
    },
    'verify deletion': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.not.equals('A Set Name Snippet');
    },
    after: (browser) => {
        browser.end();
    }
};
