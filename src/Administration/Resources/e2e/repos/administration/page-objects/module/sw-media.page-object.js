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
            .clickContextMenuItem('.sw-media-upload__button-context-menu', {
                menuActionSelector: '.sw-media-upload__button-url-upload'
            })
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', imgPath)
            .click(`${this.elements.modal}__footer .sw-button--primary`)
            .waitForElementVisible(`${this.elements.alert}--success`)
            .click(`${this.elements.alert}__close`)
            .waitForElementVisible(this.elements.previewItem);
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

    openMediaModal(action, itemPosition = null) {
        const item = itemPosition !== null ? `${this.elements.gridItem}--${itemPosition}` : this.elements.mediaItem;

        this.browser
            .waitForElementVisible(item)
            .moveToElement(item, 5, 5)
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: action,
                scope: item
            })
            .waitForElementVisible('.sw-modal__title');
    }

    moveMediaItem(name, {
        itemType,
        position = 0,
        listingPosition = 0
    }) {
        let mediaItem = this.elements.mediaItem;

        let contextMenuItemSelector = '.sw-media-context-item__move-media-action';

        if (itemType === 'folder') {
            mediaItem = `${this.elements.gridItem}--${position}`;
            contextMenuItemSelector = '.sw-media-context-item__move-folder-action';
        }

        this.browser
            .waitForElementVisible(mediaItem)
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: contextMenuItemSelector,
                scope: mediaItem
            })
            .expect.element(this.elements.modalTitle).to.have.text.that.equals(`Move "${name}"`);
        this.browser.expect.element('.sw-media-modal-move__confirm').to.not.be.enabled;

        this.browser
            .waitForElementVisible('.sw-media-folder-content__folder-listing')
            .click(`.sw-media-folder-content__list-item--${listingPosition}`)
            .expect.element('.sw-media-modal-move__confirm').to.be.enabled;
        this.browser.click('.sw-media-modal-move__confirm');

        if (itemType === 'folder') {
            this.browser
                .checkNotification('Media items successfully moved', '.sw-notifications__notification--1')
                .checkNotification(`Folder "${name}" has been moved successfully.`);
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
