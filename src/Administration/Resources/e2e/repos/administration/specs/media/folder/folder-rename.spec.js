const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

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
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton)
            .waitForElementVisible('.icon--folder-thumbnail-back')
            .waitForElementVisible(page.elements.smartBarHeader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back and edit folder name via context menu': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcrumbs-back-to-root')
            .click('.icon--folder-breadcrumbs-back-to-root')
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem('.sw-media-context-item__rename-folder-action', page.elements.contextMenuButton)
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
        page.openMediaModal(page.elements.showSettingsAction, 0);

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
            .waitForElementVisible('.sw-media-folder-info')
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('Edith Finch');

        browser
            .fillField('input[name=sw-field--draft]', 'What remains of Ediths Name', true)
            .waitForElementVisible('.sw-confirm-field__button--submit')
            .click('.sw-confirm-field__button--submit');
    },
    'verify changed folder name another time': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-index__parent-folder')
            .click('.sw-media-index__parent-folder')
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('What remains of Ediths Name');
    },
    after: (browser) => {
        browser.end();
    }
};
