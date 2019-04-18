const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'rename', 'media-rename'],
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'upload and create new media item': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'rename media file using sidebar': (browser) => {
        const page = mediaPage(browser);

        browser
            .click(`${page.elements.mediaItem} .sw-media-preview__item`)
            .waitForElementVisible('.sw-media-quickinfo')
            .fillField('.sw-media-quickinfo-metadata-name input', 'new file name', true)
            .click('.sw-media-quickinfo-metadata-name .sw-confirm-field__button--submit')
            .waitForElementPresent(`${page.elements.mediaItem} .sw-media-base-item__loader`)
            .waitForElementNotPresent(`${page.elements.mediaItem} .sw-media-base-item__loader`)
            .expect.element(`${page.elements.mediaItem} ${page.elements.baseItemName}`).to.have.text.that.contains('new file name');
    }
};
