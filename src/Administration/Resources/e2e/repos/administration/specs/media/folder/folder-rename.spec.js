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
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back and edit folder name via context menu': (browser) => {
        const page = mediaPage(browser);

        browser
            .clickContextMenuItem('.sw-media-context-item__rename-folder-action', page.elements.contextMenuButton)
            .waitForElementVisible(`${page.elements.folderNameInput}`)
            .setValue(page.elements.folderNameInput, [browser.Keys.CONTROL, 'a'])
            .setValue(page.elements.folderNameInput, browser.Keys.DELETE)
            .fillField(page.elements.folderNameInput, 'Edith gets a new name', true)
            .setValue(page.elements.folderNameInput, browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader');
    },
    'verify changed folder name': (browser) => {
        const page = mediaPage(browser);

        browser.expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Edith gets a new name');
    },
    'edit folder name via settings modal': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .fillField('input[name=sw-field--folder-name]', 'Edith Finch', true)
            .click('.sw-media-modal-folder-settings__confirm')
            .checkNotification('Settings have been saved successfully');
    },
    'verify changed folder name again': (browser) => {
        const page = mediaPage(browser);

        browser.expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Edith Finch');
    },
    'edit folder name via sidebar': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(page.elements.baseItem)
            .click(page.elements.baseItem)
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('Edith Finch');

        browser
            .fillField('input[name=sw-field--draft]', 'What remains of Ediths Name', true)
            .click('.sw-confirm-field__button--submit');
    },
    'verify changed folder name another time': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('What remains of Ediths Name');
    }
};
