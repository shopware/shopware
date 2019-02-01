const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

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
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .click(`${page.elements.gridItem}--0`)
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
            .assert.containsText('.sw-media-quickinfo-metadata-size', '501.38KB');
    },
    after: (browser) => {
        browser.end();
    }
};
