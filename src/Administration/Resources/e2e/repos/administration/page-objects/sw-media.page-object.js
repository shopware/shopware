class MediaPageObject {
    constructor(browser) {
        this.browser = browser;
        this.elements = {};
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
}

module.exports = (browser) => {
    return new MediaPageObject(browser);
};
