class MediaPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {
            previewItem: '.sw-media-preview__item',
            baseItem: '.sw-media-base-item',
            folderNameInput: 'input[name=media-item-name]',
            folderNameLabel: '.sw-media-folder-item .sw-media-base-item__name',
            mediaNameLabel: '.sw-media-media-item .sw-media-base-item__name',
            showMediaAction: '.sw-media-context-item__show-media-action',
            showSettingsAction: '.sw-media-context-item__open-settings-action',
            saveSettingsAction: '.sw-media-modal-folder-settings__confirm'
        };
    }

    uploadImageViaURL(imgPath) {
        this.browser
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', imgPath)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close')
            .waitForElementVisible(this.elements.previewItem);
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
            .waitForElementVisible(this.elements.previewItem)
            .moveToElement(this.elements.previewItem, 5, 5)
            .clickContextMenuItem(action, '.sw-context-button__button')
            .waitForElementVisible('.sw-modal__title');
    }

    createFolder(name, child = false) {
        this.browser
            .waitForElementVisible('.sw-media-index__create-folder-action')
            .click('.sw-media-index__create-folder-action')
            .waitForElementNotPresent('.sw-empty-state__title')
            .waitForElementVisible('.icon--folder-thumbnail')
            .fillField(this.elements.folderNameInput, name)
            .setValue(this.elements.folderNameInput, this.browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader')
            .waitForElementVisible(this.elements.folderNameLabel)
            .waitForText('Settings');

        if (child) {
            this.browser
                .expect.element(`.sw-media-folder-item:last-child .sw-media-base-item__name`).to.have.text.that.equals(name);
        } else {
            this.browser.expect.element(this.elements.folderNameLabel).to.have.text.that.equals(name);
        }
    }

    setThumbnailSize(width, height) {
        this.browser
            .fillField('input[name=sw-field--width', width)
            .waitForElementVisible('.sw-media-add-thumbnail-form__lock')
            .waitForElementVisible('.sw-media-add-thumbnail-form__input-height.is--disabled');

        if (height) {
            this.browser
                .click('.sw-media-add-thumbnail-form__lock')
                .waitForElementNotPresent('.sw-media-add-thumbnail-form__input-height.is--disabled')
                .fillField('input[name=sw-field--height]', height, true)
                .waitForElementVisible('.sw-media-folder-settings__add-thumbnail-size-action')
                .waitForElementNotPresent('.sw-media-folder-settings__add-thumbnail-size-action.is--disabled')
                .click('.sw-media-folder-settings__add-thumbnail-size-action')
                .waitForElementVisible('.sw-media-modal-folder-settings__thumbnail-size-entry')
                .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals(`${width}x${height}`);
        } else {
            this.browser
                .click('.sw-media-folder-settings__add-thumbnail-size-action')
                .waitForElementVisible('.sw-media-modal-folder-settings__thumbnail-size-entry')
                .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals(`${width}x${width}`);
        }
    }
}

module.exports = (browser) => {
    return new MediaPageObject(browser);
};
