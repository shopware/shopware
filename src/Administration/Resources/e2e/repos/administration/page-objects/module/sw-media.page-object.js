const GeneralPageObject = require('../sw-general.page-object');

class MediaPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                previewItem: '.sw-media-preview__item',
                folderPreviewItem: '.sw-media-base-item__preview-container',
                baseItem: '.sw-media-base-item',
                gridItem: '.sw-media-grid-item__item',
                mediaItem: '.sw-media-media-item',
                folderItem: '.sw-media-folder-item',
                folderNameInput: 'input[name=media-item-name]',
                folderNameLabel: '.sw-media-folder-item .sw-media-base-item__name',
                folderBreadcrumbBack: '.icon--folder-breadcrumbs-dropdown',
                mediaNameLabel: '.sw-media-media-item .sw-media-base-item__name',
                showMediaAction: '.sw-media-context-item__show-media-action',
                showSettingsAction: '.sw-media-context-item__open-settings-action',
                saveSettingsAction: '.sw-media-modal-folder-settings__confirm'
            }
        };
    }

    uploadImageViaURL(imgPath) {
        this.browser
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', imgPath)
            .click(`${this.elements.modal}__footer .sw-button--primary`)
            .waitForElementVisible(`${this.elements.alert}--success`)
            .click(`${this.elements.alert}__close`)
            .waitForElementVisible(this.elements.previewItem);
    }

    deleteImage() {
        this.browser
            .clickContextMenuItem(`${this.elements.contextMenu}-item--danger`, this.elements.contextMenuButton)
            .waitForElementVisible(`div.${this.elements.modal}.${this.elements.modal}--small.sw-media-modal-delete`)
            .waitForElementVisible(`${this.elements.modal}__footer .sw-media-modal-delete__confirm`)
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent(`${this.elements.modal}__footer`);
    }

    openMediaIndex() {
        this.browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/media/index',
                menuTitle: 'Media',
                index: 4
            })
            .assert.containsText(`${this.elements.adminMenu}__navigation-list-item.sw-media span.collapsible-text`, 'Media')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]');
    }

    openMediaModal(action, itemPosition) {
        this.browser
            .waitForElementVisible(`${this.elements.gridItem}--${itemPosition} ${this.elements.baseItem}`)
            .moveToElement(this.elements.baseItem, 5, 5)
            .clickContextMenuItem(action, '.sw-context-button__button', `${this.elements.gridItem}--0`)
            .waitForElementVisible('.sw-modal__title');
    }

    createFolder(name, position = 0) {
        this.browser
            .waitForElementVisible('.sw-media-index__create-folder-action')
            .click('.sw-media-index__create-folder-action')
            .waitForElementVisible(`${this.elements.gridItem}--0 .icon--folder-thumbnail`)
            .fillField(this.elements.folderNameInput, name)
            .setValue(this.elements.folderNameInput, this.browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader');

        this.browser.expect.element(`${this.elements.gridItem}--${position} .sw-media-base-item__name`).to.have.text.that.equals(name);
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
                .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals(`${width}x${height}`);
        } else {
            this.browser
                .click('.sw-media-folder-settings__add-thumbnail-size-action')
                .expect.element('.sw-media-modal-folder-settings__thumbnail-size-entry label').to.have.text.that.equals(`${width}x${width}`);
        }
    }
}

module.exports = (browser) => {
    return new MediaPageObject(browser);
};
