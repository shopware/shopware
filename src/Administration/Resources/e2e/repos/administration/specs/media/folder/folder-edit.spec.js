const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-edit', 'edit'],
    '@disabled': !flags.isActive('next1207'),
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'create and verify new folder': (browser) => {
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
            .clickContextMenuItem('.sw-media-context-item__rename-media-action', '.sw-context-button__button')
            .waitForElementVisible(`${page.elements.folderNameLabel}-field`)
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
        page.openFolderSettingsModal();

        browser
            .fillField('input[name=sw-field--folder-name]', 'Edith Finch')
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
    after: (browser) => {
        browser.end();
    }
};
