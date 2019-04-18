const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'upload', 'media-upload'],
    '@disabled': true,
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'upload and create new media item': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'look for the items in media index': (browser) => {
        const page = mediaPage(browser);
        browser.waitForElementVisible(page.elements.mediaNameLabel);
    },
    'click preview thumbnail to open sidebar': (browser) => {
        const page = mediaPage(browser);

        browser
            .click(`${page.elements.mediaItem} ${page.elements.previewItem}`)
            .waitForElementVisible('.sw-media-quickinfo');
    },
    'verify meta data': (browser) => {
        browser
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(1)', 'Name:')
            .assert.value('.sw-media-quickinfo-metadata-name input', 'sw-login-background')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(2)', 'File type:')
            .assert.containsText('.sw-media-quickinfo-metadata-file-type', 'PNG')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(5)', 'MIME-Type:')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'image/png')
            .assert.containsText('.sw-media-quickinfo-metadata-item__term:nth-of-type(6)', 'Size:')
            .assert.containsText('.sw-media-quickinfo-metadata-size', '501.38KB');
    }
};
