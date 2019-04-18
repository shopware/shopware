const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-delete', 'delete'],
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'upload and create new media item': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .click('.sw-media-upload__button-context-menu')
            .click('.sw-media-upload__button-url-upload')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementNotPresent('.sw-media-url-form')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.sw-media-base-item__loader')
            .waitForElementVisible(`${page.elements.alert}--success`)
            .click(page.elements.alertClose);
    },
    'delete item and verify that': (browser) => {
        const page = mediaPage(browser);

        browser
            .click(`${page.elements.mediaItem} .sw-media-preview__item`)
            .waitForElementVisible('.sw-media-quickinfo')
            .click('li.quickaction--delete')
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.equals('Are you sure you want to delete "sw-login-background.png"?');

        browser
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent(`${page.elements.modal}l__footer`)
            .waitForElementNotPresent(page.elements.loader)
            .checkNotification('Files have been deleted successfully', `${page.elements.notification}--1`)
            .checkNotification('File "sw-login-background.png" has been deleted successfully');
    }
};
