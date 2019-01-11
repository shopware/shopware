const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-dissolve', 'dissolve'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    '@disabled': !flags.isActive('next1207'),
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'verify creation of the new folder and navigate': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem(page.elements.showMediaAction, '.sw-context-button__button')
            .waitForElementVisible('.icon--folder-breadcums-parent')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'upload image to folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL();
    },
    'dissolve folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcums-dropdown')
            .click('.icon--folder-breadcums-dropdown')
            .waitForElementNotPresent(page.elements.previewItem)
            .clickContextMenuItem('.sw-media-context-item__dissolve-media-action', '.sw-context-button__button')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal__body', `Are you sure you want to dissolve "${global.MediaFixtureService.mediaFolderFixture.name}" ?`)
            .waitForElementVisible('.sw-media-modal-folder-dissolve__confirm')
            .click('.sw-media-modal-folder-dissolve__confirm')
            .checkNotification(`Folder "${global.MediaFixtureService.mediaFolderFixture.name}" has been dissolved successfully`, false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), 'Folder ${global.MediaFixtureService.mediaFolderFixture.name} has been dissolved successfully')]`)
            .useCss()
            .checkNotification('Files have been deleted successfully', false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), 'Files have been deleted successfully')]`)
            .useCss();
    },
    'verify if folder was removed and images persisted': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementPresent(page.elements.previewItem)
            .waitForElementVisible(page.elements.folderNameLabel)
            .getValue(page.elements.folderNameLabel, function checkValueNotPresent(result) {
                this.assert.notEqual(result, global.MediaFixtureService.mediaFolderFixture.name);
            })
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('sw-login-background.png');
    },
    after: (browser) => {
        browser.end();
    }
};



