const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'upload', 'media-upload'],
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'upload and create new media item': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);

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
            .waitForElementVisible('.sw-media-quickinfo');
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
    after: (browser) => {
        browser.end();
    }
};
