module.exports = {
    '@tags': ['media-upload'],
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index','Media')
            .assert.urlContains('#/sw/media/index');
    },
    'open media index and change catalog': (browser) => {
        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-media span.collapsible-text', 'Media')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]')
            .waitForElementVisible('div.sw-media-grid.sw-media-index__catalog-grid')
            .moveToElement('.sw-media-index__catalog-grid .sw-media-grid__content-cell', 15, 15)
            .click('.sw-media-index__catalog-grid .sw-media-grid__content-cell')
            .waitForElementVisible('div.sw-page.sw-media-catalog');
    },
    'upload and create first media item': (browser) => {
        browser
            .clickContextMenuItem('.sw-media-upload-button__button-url','.sw-media-upload-button__context-button')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .fillField('input[name=sw-field--url]',`${process.env.APP_URL}/bundles/administration/static/img/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    },
    'upload and create second media item': (browser) => {
        browser
            .clickContextMenuItem('.sw-media-upload-button__button-url','.sw-media-upload-button__context-button')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/img/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    },
    'look for both items in media index and default catalog': (browser) => {
        browser
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]')
            .waitForElementVisible('.sw-media-grid__content-cell:nth-of-type(2)')
            .click('.sw-media-index__catalog-grid .sw-media-grid__content-cell:nth-of-type(1)')
            .waitForElementVisible('.sw-media-catalog')
            .waitForElementVisible('.sw-media-grid-media-item:nth-of-type(2)');
    },
    'click preview thumbnail to open sidebar': (browser) => {
        browser
            .click('.sw-media-preview__item')
            .waitForElementVisible('.sw-sidebar-item__content');

    },
    'verify meta data': (browser) => {
        browser
            .assert.containsText('.sw-media-quickinfo-metadata-name', 'Name:')
            .assert.containsText('.sw-media-quickinfo-metadata-name', 'sw-login-background')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'MIME-Type:')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'image/png')
            .assert.containsText('.sw-media-quickinfo-metadata-size', 'Size:')
            .assert.containsText('.sw-media-quickinfo-metadata-size', '501.38KB')
            .assert.containsText('.sw-media-quickinfo-metadata-createdAt', 'Uploaded at:')
            .assert.containsText('.sw-media-quickinfo-metadata-url', 'URL:')
            .assert.containsText('.sw-media-quickinfo-metadata-width', 'Width:')
            .assert.containsText('.sw-media-quickinfo-metadata-width', '589px')
            .assert.containsText('.sw-media-quickinfo-metadata-height', 'Height:')
            .assert.containsText('.sw-media-quickinfo-metadata-height', '637px');
    },
    'delete first item and check deletion verification': (browser) => {
        browser
            .click('li.quickaction--delete')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-login-background" ?')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent('.sw-modal__footer')
            .waitForElementVisible ('.sw-alert--notification')
            .click('.sw-alert__close');
    },
    'delete second item and verify deletion': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger','.sw-context-button__button')
            .waitForElementVisible('.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-login-background" ?')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementVisible('.sw-empty-state')
            .end();
    }
};
