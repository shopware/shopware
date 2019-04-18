const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-ignore-translations', 'translation', 'language-switch'],
    '@disabled': true,
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser.waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`);
    },
    'upload an image': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'change language to german and verify that items did not change': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.sw-language-switch')
            .waitForElementNotPresent('.sw-field__select-load-placeholder')
            .waitForElementNotPresent(page.elements.loader);

        browser.expect.element('.sw-select-option:nth-of-type(1)').to.have.text.that.equals('Deutsch');
        browser
            .click('.sw-select-option:nth-of-type(1)')
            .waitForElementNotPresent('.sw-field__select-load-placeholder');

        browser.expect.element(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
        browser.expect.element(`${page.elements.gridItem}--1 ${page.elements.baseItemName}`).to.have.text.that.equals('sw-login-background.png');

        page.createFolder('German crowd');
    }
};
