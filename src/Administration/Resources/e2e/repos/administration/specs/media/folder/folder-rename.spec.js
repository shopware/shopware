const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-rename', 'rename'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'verify the available folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(`${page.elements.gridItem}--0`).to.have.text.that.contains(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back and edit folder name via context menu': (browser) => {
        const page = mediaPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-media-context-item__rename-folder-action'
            })
            .waitForElementVisible(`${page.elements.folderNameInput}`)
            .setValue(page.elements.folderNameInput, [browser.Keys.CONTROL, 'a'])
            .setValue(page.elements.folderNameInput, browser.Keys.DELETE)
            .fillField(page.elements.folderNameInput, 'An Edith gets a new name', true)
            .setValue(page.elements.folderNameInput, browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader');
    },
    'verify changed folder name': (browser) => {
        const page = mediaPage(browser);

        browser.expect.element(`${page.elements.gridItem}--0`).to.have.text.that.contains('An Edith gets a new name');
    },
    'edit folder name via settings modal': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .fillField('input[name=sw-field--folder-name]', 'An Edith Finch', true)
            .click('.sw-media-modal-folder-settings__confirm')
            .checkNotification('Settings have been saved successfully');
    },
    'verify changed folder name again': (browser) => {
        const page = mediaPage(browser);

        browser.expect.element(`${page.elements.gridItem}--0`).to.have.text.that.contains('An Edith Finch');
    },
    'edit folder name via sidebar': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__name`)
            .click(`${page.elements.gridItem}--0 .sw-media-base-item__name`)
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('An Edith Finch');

        browser
            .fillField('input[name=sw-field--draft]', '1. What remains of Ediths Name', true)
            .click('.sw-confirm-field__button--submit');
    },
    'verify changed folder name another time': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(`${page.elements.gridItem}--0`).to.have.text.that.contains('1. What remains of Ediths Name');
    }
};
