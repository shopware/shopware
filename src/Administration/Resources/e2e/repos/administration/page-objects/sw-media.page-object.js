class MediaPageObject {
    constructor(browser) {
        this.browser = browser;

        this.elements = {};
        this.elements.urlUploadAction = '.sw-media-upload__url-upload-action';
        this.elements.previewItem = '.sw-media-preview__item';
        this.elements.baseItem = '.sw-media-preview__item';
    }

    uploadImageViaURL(imgPath) {
        this.browser
            .waitForElementVisible(this.elements.urlUploadAction)
            .click(this.elements.urlUploadAction)
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', imgPath)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close')
            .waitForElementVisible(this.elements.previewItem);
    }

    deleteImage() {
        this.browser
            .clickContextMenuItem('.sw-context-menu-item--danger','.sw-context-button__button')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent('.sw-modal__footer');
    }

    openMediaFolder(){
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
}

module.exports = (browser) => {
    return new MediaPageObject(browser);
};
