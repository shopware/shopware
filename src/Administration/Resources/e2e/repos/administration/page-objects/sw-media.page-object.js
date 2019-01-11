class MediaPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {};

        this.elements.previewItem = '.sw-media-preview__item';
        this.elements.folderNameInput = 'input[name=media-item-name]';
        this.elements.folderNameLabel = '.sw-media-base-item__name';
        this.elements.showMediaAction = '.sw-media-context-item__show-media-action';
        this.elements.showSettingsAction = '.sw-media-context-item__open-settings-action';
        this.elements.saveSettingsAction = '.sw-media-modal-folder-settings__confirm';
    }

    uploadImageViaURL() {
        this.browser
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    }

    deleteImage() {
        this.browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent('.sw-modal__footer');
    }

    openMediaIndex() {
        this.browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-media span.collapsible-text', 'Media')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]');
    }

    openMediaModal(action) {
        this.browser
            .clickContextMenuItem(action, '.sw-context-button__button')
            .waitForElementVisible('.sw-modal__title');
    }

    createFolder(name, child = false) {
        this.browser
            .waitForElementVisible('.sw-media-index__create-folder-action')
            .click('.sw-media-index__create-folder-action')
            .waitForElementNotPresent('.sw-empty-state__title')
            .waitForElementVisible('.icon--folder-thumbnail')
            .waitForElementVisible(`${this.elements.folderNameLabel}-field`)
            .fillField(this.elements.folderNameInput, name)
            .setValue(this.elements.folderNameInput, this.browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader')
            .waitForElementVisible(this.elements.folderNameLabel)
            .waitForText('Settings');

        if (child) {
            this.browser
                .expect.element(`.sw-media-folder-item:last-child ${this.elements.folderNameLabel}`).to.have.text.that.equals(name);
        } else {
            this.browser.expect.element(this.elements.folderNameLabel).to.have.text.that.equals(name);
        }


    }

    openFolderSettingsModal() {
        this.browser
            .clickContextMenuItem(this.elements.showSettingsAction, '.sw-context-button__button')
            .waitForElementVisible('.sw-modal')
            .waitForElementVisible('.sw-media-folder-settings__settings-tab');
    }
}

module.exports = (browser) => {
    return new MediaPageObject(browser);
};
