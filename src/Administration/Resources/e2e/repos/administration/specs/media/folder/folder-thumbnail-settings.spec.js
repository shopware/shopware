const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-thumbnails', 'thumbnails'],
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
    'open thumbnail settings': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnails-list-container');
    },
    'set general thumbnail settings': (browser) => {
        browser
            .tickCheckbox('input[name=sw-field--configuration-keepAspectRatio]', false)
            .fillField('input[name=sw-field--configuration-thumbnailQuality]', '90', true);
    },
    'create first thumbnail size with locked height': (browser) => {
        const page = mediaPage(browser);
        page.setThumbnailSize('800');

        browser
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully');
    },
    'remove first thumbnail size and create second size with separate height afterwards': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnails-list-container')
            .waitForElementVisible('.sw-media-modal-folder-settings__switch-mode')
            .click('.sw-media-modal-folder-settings__switch-mode')
            .waitForElementVisible('.sw-media-modal-folder-settings__thumbnail-size-entry')
            .waitForElementVisible('.sw-media-modal-folder-settings__delete-thumbnail')
            .click('.sw-media-modal-folder-settings__delete-thumbnail')
            .waitForElementNotPresent('.sw-media-modal-folder-settings__thumbnail-size-entry');

        page.setThumbnailSize('1920', '1080');

        browser
            .tickCheckbox('input[name=thumbnail-size-active]', true)
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully');
    },
    'create child folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton,`${page.elements.gridItem}--0`)
            .waitForElementVisible('.icon--folder-thumbnail-back')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);

        page.createFolder('Child folder');
    },
    'check inheritance of parent thumbnail settings and sizes': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .expect.element('input[name=sw-field--configuration-keepAspectRatio]').to.not.be.selected;

        browser.expect.element('input[name=sw-field--configuration-thumbnailQuality]').to.have.value.that.equals('90');

        browser
            .assert.containsText('.sw-media-modal-folder-settings__thumbnail-size-entry label', '1920x1080')
            .expect.element('input[name=thumbnail-size-active]').to.be.selected;
    },
    'deactivate inheritance': (browser) => {
        const page = mediaPage(browser);

        browser
            .tickCheckbox('input[name=sw-field--folder-useParentConfiguration]', false)
            .tickCheckbox('input[name=sw-field--configuration-keepAspectRatio]', true)
            .fillField('input[name=sw-field--configuration-thumbnailQuality]', '75', true)
            .waitForElementVisible('.sw-media-modal-folder-settings__switch-mode')
            .click('.sw-media-modal-folder-settings__switch-mode')
            .waitForElementVisible('.sw-media-modal-folder-settings__delete-thumbnail')
            .click('.sw-media-modal-folder-settings__delete-thumbnail')
            .waitForElementNotPresent('.sw-media-modal-folder-settings__thumbnail-size-entry');

        browser
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully');
    },
    'navigate back to parent folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcrumbs-back-to-root')
            .click('.router-link-active')
            .expect.element(page.elements.folderNameLabel).to.have.value.that.equals(global.MediaFixtureService.mediaFolderFixture.name);

    },
    'verify deactivated inheritance': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser
            .waitForElementVisible('.sw-media-folder-settings__thumbnails-tab')
            .click('.sw-media-folder-settings__thumbnails-tab')
            .expect.element('input[name=sw-field--configuration-keepAspectRatio]').to.not.be.selected;

        browser.expect.element('input[name=sw-field--configuration-thumbnailQuality]').to.have.value.that.equals('90');

        browser.expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals('1920x1080');
        browser.expect.element('input[name=thumbnail-size-active]').to.be.selected;
    },
    after: (browser) => {
        browser.end();
    }
};
