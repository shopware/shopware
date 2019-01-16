const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-replace', 'replace'],
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'upload and verify new media item': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
        browser.expect.element('.sw-media-base-item__name').to.have.text.that.equals('sw-login-background.png');
    },
    'open replace modal': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal('.sw-media-context-item__replace-media-action');
    },
    'ensure image cannot be replaced with empty input': (browser) => {
        browser
            .getAttribute('.sw-media-replace__replace-media-action', 'disabled', function (result) {
                this.assert.equal(result.value, 'true');
            })
            .waitForElementVisible('.sw-media-upload__switch-mode')
            .click('.sw-media-upload__switch-mode')
            .getAttribute('.sw-media-replace__replace-media-action', 'disabled', function (result) {
                this.assert.equal(result.value, 'true');
            });
    },
    'replace image with a valid one': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-url-form__url-input')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`)
            .click('.sw-media-url-form__submit-button')
            .waitForElementNotPresent('input[name=sw-field--url]')
            .waitForElementVisible(page.elements.previewItem)
            .waitForElementVisible('.sw-media-replace__replace-media-action')
            .click('.sw-media-replace__replace-media-action')
            .checkNotification('File has been saved successfully', false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), 'File has been saved successfully')]`)
            .useCss()
            .checkNotification('File replaced');
    },
    'verify if image was replaced correctly': (browser) => {
        browser.expect.element('.sw-media-base-item__name').to.have.text.that.equals('sw-test-image.png');

        browser
            .waitForElementVisible('.sw-media-base-item')
            .click('.sw-media-base-item')
            .waitForElementVisible('.sw-media-quickinfo__media-preview')
            .waitForElementVisible('.sw-sidebar-item__title')
            .assert.containsText('.sw-sidebar-item__title', 'Quick info')
            .getLocationInView('.sw-media-quickinfo__metadata-list');

        browser.expect.element('input[name=sw-field--draft]').to.have.value.that.equals('sw-test-image');
        browser.expect.element('.sw-media-quickinfo-metadata-file-type').to.have.text.that.equals('PNG');
        browser.expect.element('.sw-media-quickinfo-metadata-mimeType').to.have.text.that.equals('image/png');
    },
    after: (browser) => {
        browser.end();
    }
};
