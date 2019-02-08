const GeneralPageObject = require('../sw-general.page-object');

class MediaPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                previewItem: '.sw-media-preview__item',
                folderPreviewItem: '.sw-media-base-item__preview-container',
                baseItem: '.sw-media-base-item',
                baseItemName: '.sw-media-base-item__name',
                gridItem: '.sw-media-grid-item__item',
                mediaItem: '.sw-media-media-item',
                folderItem: '.sw-media-folder-item',
                folderNameInput: 'input[name=media-item-name]',
                folderNameLabel: '.sw-media-folder-item .sw-media-base-item__name',
                folderBreadcrumbBack: '.icon--multicolor-folder-breadcrumbs-dropdown',
                mediaNameLabel: '.sw-media-media-item .sw-media-base-item__name',
                showMediaAction: '.sw-media-context-item__show-media-action',
                showSettingsAction: '.sw-media-context-item__open-settings-action',
                saveSettingsAction: '.sw-media-modal-folder-settings__confirm'
            }
        };
    }

    uploadImageViaURL(imgPath) {
        this.browser
            .waitForElementNotPresent(this.elements.loader)
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
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent(`${this.elements.modal}__footer`);
    }

    openMediaIndex() {
        this.browser
            .openMainMenuEntry({
                targetPath: '#/sw/media/index',
                mainMenuId: 'sw-media'
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

    moveMediaItem(name, itemType, position = 0) {
        let contextMenuItemSelector = '.sw-media-context-item__move-media-action';

        if (itemType === 'folder') {
            contextMenuItemSelector = '.sw-media-context-item__move-folder-action';
        }

        this.browser
            .waitForElementVisible(`${this.elements.gridItem}--${position}`)
            .clickContextMenuItem(contextMenuItemSelector, this.elements.contextMenuButton, `${this.elements.gridItem}--${position} `)
            .expect.element(this.elements.modalTitle).to.have.text.that.equals(`Move "${name}"`);
        this.browser.expect.element('.sw-media-modal-move__confirm').to.not.be.enabled;

        this.browser
            .click('.sw-media-folder-content__folder-listing')
            .expect.element('.sw-media-modal-move__confirm').to.be.enabled;
        this.browser.click('.sw-media-modal-move__confirm');

        if (itemType === 'folder') {
            this.browser
                .checkNotification('Media items successfully moved', '.sw-notifications__notification--1')
                .checkNotification('Folder "First folder" has been moved successfully.');
        } else {
            this.browser.checkNotification('Media items successfully moved');
        }
    }

    createFolder(name, position = 0) {
        this.browser
            .waitForElementNotPresent(this.elements.loader)
            .click('.sw-media-index__create-folder-action')
            .waitForElementVisible(`${this.elements.gridItem}--0 .icon--multicolor-folder-thumbnail`)
            .fillField(this.elements.folderNameInput, name)
            .setValue(this.elements.folderNameInput, this.browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-media-base-item__loader');

        this.browser.expect.element(`${this.elements.gridItem}--${position} ${this.elements.baseItemName}`).to.have.text.that.equals(name);
    }

    createDefaultFolder(defaultFolderEntity, name, position = 0) {
        this.createFolder(name, position);
        this.openMediaModal(this.elements.showSettingsAction, position);

        this.browser.click('.sw-media-folder-settings-modal__default-folder-select')
            .waitForElementVisible('.sw-select__results')
            .setValue('.sw-select__input-single', defaultFolderEntity)
            .waitForElementNotPresent('.sw-loader')
            .click('.sw-select-option--0')
            .click('.sw-media-modal-folder-settings__confirm')
            .waitForElementNotPresent('.sw-media-modal-folder-settings')
            .checkNotification('Settings have been saved successfully.');
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
