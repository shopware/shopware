module.exports = {
    '@tags': ['media-upload'],
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'upload and create first media item': (browser) => {
        browser
            .waitForElementVisible('.sw-media-upload__url-upload-action')
            .click('.sw-media-upload__url-upload-action')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    },
    'upload and create second media item': (browser) => {
        browser
            .waitForElementVisible('.sw-media-upload__url-upload-action')
            .click('.sw-media-upload__url-upload-action')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    },
    'look for both items in media index': (browser) => {
        browser
            .waitForElementVisible('.sw-media-media-item:nth-of-type(1)')
            .waitForElementVisible('.sw-media-media-item:nth-of-type(2)');
    },
    'click preview thumbnail to open sidebar': (browser) => {
        browser
            .click('.sw-media-preview__item:nth-of-type(1)')
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
            .assert.containsText('.sw-media-quickinfo-metadata-url', 'URL:');
    },
    'delete first item and check deletion verification': (browser) => {
        browser
            .click('li.quickaction--delete')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-login-background (1).png" ?')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent('.sw-modal__footer')
            .checkNotification('Media item successfully deleted.');
    },
    'delete second item and verify deletion': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button')
            .waitForElementVisible('.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-login-background.png" ?')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementVisible('.sw-empty-state')
            .end();
    }
};
