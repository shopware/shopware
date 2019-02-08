const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-ignore-translations', 'translation', 'language-switch'],
    before: (browser, done) => {
        if (global.flags.isActive('next1207')) {
            global.MediaFixtureService.setFolderFixture().then(() => {
                done();
            });
        } else {
            done();
        }
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        if (global.flags.isActive('next1207')) {
            browser
                .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`);
        }
    },
    'upload an image': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'change language to german and verify that items did not change': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-language-switch')
            .click('.sw-language-switch')
            .waitForElementNotPresent('.sw-field__select-load-placeholder')
            .waitForElementNotPresent(page.elements.loader);

        browser.expect.element('.sw-select-option:last-child').to.have.text.that.equals('Deutsch').before(browser.globals.waitForConditionTimeout);
        browser
            .click('.sw-select-option:last-child')
            .waitForElementNotPresent('.sw-field__select-load-placeholder');

        if (global.flags.isActive('next1207')) {
            browser.expect.element(`${page.elements.gridItem}--0 .sw-media-base-item__name`).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
            browser.expect.element(`${page.elements.gridItem}--1 .sw-media-base-item__name`).to.have.text.that.equals('sw-login-background.png');

            page.createFolder('German crowd');
        } else {
            browser.expect.element(`${page.elements.gridItem}--0 .sw-media-base-item__name`).to.have.text.that.equals('sw-login-background.png');
        }
    },
    after: (browser) => {
        browser.end();
    }
};
