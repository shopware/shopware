/* global cy */
import elements from '../sw-general.page-object';

export default class MediaPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                uploadInput: '#files',
                previewItem: '.sw-media-preview-v2__item',
                previewPlaceholder: '.sw-media-preview-v2__placeholder',
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
                saveSettingsAction: '.sw-media-modal-folder-settings__confirm',
                mediaQuickInfo: '.sw-media-quickinfo'
            }
        };
    }

    uploadImageUsingUrl(path) {
        cy.get('.sw-media-url-form').should('be.visible');
        cy.get('input[name=sw-field--url]').should('be.visible')
            .type(path);
        cy.get('.sw-media-url-form__submit-button').click();

        cy.get('.sw-media-preview-v2__item').should('be.visible');
        return this;
    }

    uploadImageUsingFileUpload(path, name) {
        cy.fixture(path).then(fileContent => {
            cy.get(this.elements.uploadInput).upload(
                {
                    fileContent,
                    fileName: name,
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });

        cy.get('.sw-media-preview-v2__item').should('be.visible');

        return this;
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

        cy.clickContextMenuItem(
            contextMenuItemSelector,
            this.elements.contextMenuButton,
            mediaItem
        );
        cy.get(this.elements.modalTitle).contains(`Move "${name}"`);
        cy.get('.sw-media-modal-move__confirm').should('be.disabled');

        cy.get(`.sw-media-folder-content__list-item--${listingPosition}`).click();
        cy.get('.sw-media-modal-move__confirm').should('not.be.disabled');
        cy.get('.sw-media-modal-move__confirm').click();

        if (itemType === 'folder') {
            cy.awaitAndCheckNotification('Media items have been moved.', {
                position: 1
            });
        } else {
            cy.awaitAndCheckNotification('Media items have been moved.');
        }
    }

    setThumbnailSize(size, position = 0) {
        cy.get('input[name=sw-field--width').type(size.width);
        cy.get('input[name=sw-field--width').type('{enter}');

        if (size.height) {
            cy.get('.sw-media-add-thumbnail-form__lock').click();
            cy.get('input[name=sw-field--height]').clear();
            cy.get('input[name=sw-field--height]').clear();
            cy.get('input[name=sw-field--height]').type(size.height);
            cy.get('input[name=sw-field--height]').type('{enter}');
            cy.get('.sw-media-folder-settings__add-thumbnail-size-action.is--disabled')
                .should('not.exist');
            cy.get('.sw-media-folder-settings__add-thumbnail-size-action').click();
            cy.get(`.sw-media-modal-folder-settings__entry--${position} label`)
                .contains(`${size.width}x${size.height}`);
        } else {
            cy.get('.sw-media-folder-settings__add-thumbnail-size-action').click();
            cy.get(`.sw-media-modal-folder-settings__entry--${position} label`)
                .contains(`${size.width}x${size.width}`);
        }
    }

    createFolder(name) {
        cy.get('.sw-media-index__create-folder-action').click();
        cy.get(this.elements.folderNameInput).type(name);
        cy.get(this.elements.folderNameInput).type('{enter}');
        cy.get('.sw-media-base-item__loader').should('not.exist');
    }
}
