const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-thumbnails', 'thumbnails'],
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
    'open thumbnail settings': (browser) => {
        const page = mediaPage(browser);
        page.openFolderSettingsModal();

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnails-list-container');
    },
    'edit thumbnail sizes including creation of own maximum sizes': (browser) => {
        const page = mediaPage(browser);

        browser
            .fillField('input[name=sw-field--width', '800')
            .waitForElementVisible('.sw-media-add-thumbnail-form__lock')
            .waitForElementVisible('.sw-media-add-thumbnail-form__input-height.is--disabled')
            .click('.sw-media-add-thumbnail-form__lock')
            .waitForElementNotPresent('.sw-media-add-thumbnail-form__input-height.is--disabled')
            .fillField('input[name=sw-field--height]', '800')
            .waitForElementVisible('.sw-media-folder-settings__add-thumbnail-size-action')
            .waitForElementNotPresent('.sw-media-folder-settings__add-thumbnail-size-action.is--disabled')
            .click('.sw-media-folder-settings__add-thumbnail-size-action')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnail-size-entry')
            .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals('800x800');

        browser
            .tickCheckbox('input[name=thumbnail-size-active]', 'on')
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully');
    },
    'creating child folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem('.sw-media-context-item__show-media-action', '.sw-context-button__button')
            .waitForElementVisible('.icon--folder-breadcums-parent')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals('Fold it');

        page.createFolder('Child folder', true);
    },
    'checking usage of parent thumbnail sizes in new child folder': (browser) => {
        const page = mediaPage(browser);
        page.openFolderSettingsModal();

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnails-list-container')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnail-size-entry')
            .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals('800x800');
    },
    after: (browser) => {
        browser.end();
    }
};



