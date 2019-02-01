const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-delete', 'delete'],
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'upload and create new media item': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-upload__button-context-menu')
            .click('.sw-media-upload__button-context-menu')
            .waitForElementVisible('.sw-media-upload__button-url-upload')
            .click('.sw-media-upload__button-url-upload')
            .waitForElementVisible('.sw-media-url-form')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`)
            .click(`.sw-modal__footer .sw-button--primary`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.sw-media-base-item__loader')
            .waitForElementVisible(`${page.elements.alert}--success`)
            .click(page.elements.alertClose);
    },
    'delete item and verify that': (browser) => {
        const page = mediaPage(browser);

        browser
            .click(`${page.elements.gridItem}--0 .sw-media-preview__item`)
            .waitForElementVisible('.sw-media-quickinfo')
            .click('li.quickaction--delete')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .assert.containsText(`${page.elements.modal}__body`, 'Are you sure you want to delete "sw-login-background.png" ?')
            .waitForElementVisible(`${page.elements.modal}__footer .sw-media-modal-delete__confirm`)
            .click('.sw-media-modal-delete__confirm')
            .waitForElementNotPresent(`${page.elements.modal}l__footer`)
            .waitForElementNotPresent(page.elements.loader)
            .checkNotification('File "sw-login-background.png" has been deleted successfully', false)
            .click(page.elements.alertClose)
            .expect.element(`${page.elements.alert}__message`).to.have.text.not.equals('File "sw-login-background.png" has been deleted successfully').before(500);
        browser.checkNotification('Files have been deleted successfully');
    },
    after: (browser) => {
        browser.end();
    }
};
