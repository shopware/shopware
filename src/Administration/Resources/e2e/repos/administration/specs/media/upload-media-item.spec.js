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
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(1)', 'Name:')
            .assert.value('.sw-media-quickinfo-metadata-name input', 'sw-login-background')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(2)', 'Filetype:')
            .assert.containsText('.sw-media-quickinfo-metadata-file-type', 'PNG')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(5)', 'MIME-Type:')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'image/png')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(6)', 'Size:')
            .assert.containsText('.sw-media-quickinfo-metadata-size', '501.38KB')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(7)', 'Uploaded at:')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(8)', 'URL:');
    },
    'rename media file': (browser) => {
        browser
            .clearValue('.sw-media-quickinfo-metadata-name input')
            .setValue('.sw-media-quickinfo-metadata-name input', 'new file name')
            .click('.sw-media-quickinfo-metadata-name .sw-confirm-field__button--submit')
            .waitForElementPresent('.sw-media-media-item:nth-of-type(1) .sw-media-base-item__loader')
            .waitForElementNotPresent('.sw-media-media-item:nth-of-type(1) .sw-media-base-item__loader')
            .assert.containsText('.sw-media-media-item:nth-of-type(1) .sw-media-base-item__name', 'new file name');
    },
    after: (browser) => {
        browser.end();
    }
};
