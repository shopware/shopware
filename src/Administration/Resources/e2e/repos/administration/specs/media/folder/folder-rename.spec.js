const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-rename', 'rename'],
    '@disabled': !global.flags.isActive('next1207'),
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'verify the available folder and navigate to it': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem(page.elements.showMediaAction, '.sw-context-button__button')
            .waitForElementVisible('.icon--folder-breadcums-parent')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back and edit folder name via context menu': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcums-dropdown')
            .click('.icon--folder-breadcums-dropdown')
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem('.sw-media-context-item__rename-folder-action', '.sw-context-button__button')
            .waitForElementVisible(`${page.elements.folderNameInput}`)
            .setValue(page.elements.folderNameInput, [browser.Keys.CONTROL, 'a'])
            .setValue(page.elements.folderNameInput, browser.Keys.DELETE)
            .fillField(page.elements.folderNameInput, 'Edith gets a new name',)
            .setValue(page.elements.folderNameInput, browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader');
    },
    'verify changed folder name': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(page.elements.folderNameLabel)
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Edith gets a new name');
    },
    'edit folder name via settings modal': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction);

        browser
            .fillField('input[name=sw-field--folder-name]', 'Edith Finch', true)
            .waitForElementVisible('.sw-media-modal-folder-settings__confirm')
            .click('.sw-media-modal-folder-settings__confirm')
            .checkNotification('Settings have been saved successfully');
    },
    'verify changed folder name again': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(page.elements.folderNameLabel)
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Edith Finch');
    },
    'edit folder name via sidebar': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(page.elements.baseItem)
            .click(page.elements.baseItem)
            .expect.element('.sw-sidebar-item__title').to.have.text.that.equals('Quick info');

        browser
            .fillField('input[name=sw-field--draft]', 'What remains of Ediths Name', true)
            .waitForElementVisible('.sw-confirm-field__button--submit')
            .click('.sw-confirm-field__button--submit');
    },
    'verify changed folder name another time': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-sidebar-item__close-button')
            .click('.sw-sidebar-item__close-button')
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('What remains of Ediths Name');
    },
    'edit folder name via double click': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__name-container')
            .moveToElement('.sw-media-base-item__name-container', 5, 5).doubleClick()
            .waitForElementVisible(`${page.elements.folderNameInput}`)
            .setValue(page.elements.folderNameInput, [browser.Keys.CONTROL, 'a'])
            .setValue(page.elements.folderNameInput, browser.Keys.DELETE)
            .fillField(page.elements.folderNameInput, 'Named via Doubleclick', true)
            .setValue(page.elements.folderNameInput, browser.Keys.ENTER);
    },
    'verify changed folder name last time': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(page.elements.folderNameLabel)
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Named via Doubleclick');
    },
    after: (browser) => {
        browser.end();
    }
};
