module.exports = {
    '@tags': ['media', 'media-delete', 'delete'],
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
    'delete item and verify that': (browser) => {
        browser
            .click('.sw-media-preview__item:nth-of-type(1)')
            .waitForElementVisible('.sw-sidebar-item__content')
            .click('li.quickaction--delete')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Are you sure you want to delete "sw-login-background.png" ?')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent('.sw-modal__footer')
            .checkNotification('File "sw-login-background.png" has been deleted successfully', false)
            .click('.sw-alert__close')
            .useXpath()
            .waitForElementNotPresent(`//*[contains(text(), 'File "sw-login-background.png" has been deleted successfully')]`)
            .useCss();
    },
    after: (browser) => {
        browser.end();
    }
};
