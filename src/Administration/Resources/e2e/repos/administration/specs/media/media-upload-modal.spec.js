const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'rename', 'upload', 'replace', 'media-modal', 'media-upload-modal'],
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'upload media item': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'trigger duplicate media modal by uploading the existing media item again': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`);
    },
    'replace media file via duplicate media modal': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-field__radio-group')
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`)
            .waitForElementVisible(page.elements.alertClose)
            .click(page.elements.alertClose);

        browser.expect.element(page.elements.mediaItem).to.not.have.text.that.equals('sw-login-background_(1).png');
        browser.expect.element(page.elements.mediaItem).to.have.text.that.equals('sw-login-background.png');
    },
    'trigger duplicate media modal again by uploading the existing media item once more': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`);
    },
    'rename media file via duplicate-media-modal': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-field__radio-group')
            .click('input#sw-field--selectedOption-1')
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`)
            .waitForElementVisible(page.elements.alertClose)
            .click(page.elements.alertClose);

        browser.expect.element(`${page.elements.gridItem}--0 ${page.elements.baseItem}`).to.have.text.that.equals('sw-login-background_(1).png');
    },
    'trigger duplicate media modal one last time by uploading the existing media item': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem('.sw-media-upload__button-url-upload', '.sw-media-upload__button-context-menu')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`);
    },
    'skip media file via duplicate-media-modal': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-field__radio-group')
            .click('input#sw-field--selectedOption-2')
            .waitForElementVisible('.sw-modal__footer')
            .click(`${page.elements.modalFooter} ${page.elements.primaryButton}`);

        browser.expect.element('.sw-media-grid-item__item--0').to.have.text.that.equals('sw-login-background_(1).png');
        browser.expect.element('.sw-media-grid-item__item--1').to.have.text.that.equals('sw-login-background.png');
    },
    after: (browser) => {
        browser.end();
    }
};

