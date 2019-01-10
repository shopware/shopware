module.exports = {
    '@tags': ['media', 'upload', 'media-upload'],
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'upload and create new media item': (browser) => {
        browser
            .waitForElementVisible('.sw-media-upload__button-context-menu')
            .click('.sw-media-upload__button-context-menu')
            .waitForElementVisible('.sw-media-upload__button-url-upload')
            .click('.sw-media-upload__button-url-upload')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close');
    },
    'look and search for the items in media index': (browser) => {
        browser
            .fillGlobalSearchField('sw-login-background')
            .refresh()
            .waitForElementVisible('.sw-media-media-item:nth-of-type(1)');
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
    after: (browser) => {
        browser.end();
    }
};
