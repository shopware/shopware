const mediaPage = require('administration/page-objects/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-create', 'create'],
    '@disabled': !flags.isActive('next1207'),
    'open media listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .assert.urlContains('#/sw/media/index');
    },
    'create new folder': (browser) => {
        const page = mediaPage(browser);
        page.createFolder('Fold it');
    },
    'verify creation of the new folder and navigate': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem(page.elements.showMediaAction, '.sw-context-button__button')
            .waitForElementVisible('.icon--folder-breadcums-parent')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals('Fold it');
    },
    'upload image to folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL();
    },
    'navigate back and check if the image is assigned correctly': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcums-dropdown')
            .click('.icon--folder-breadcums-dropdown')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'check if the folder can be found in other modules': (browser) => {
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-sidebar-navigation-item')
            .click('.sw-sidebar-navigation-item')
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals('Fold it');
    },
    after: (browser) => {
        browser.end();
    }
};
