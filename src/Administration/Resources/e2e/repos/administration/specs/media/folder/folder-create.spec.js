const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-create', 'create', 'upload'],
    '@disabled': !global.flags.isActive('next1207'),
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'create new folder': (browser) => {
        const page = mediaPage(browser);
        page.createFolder(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'verify creation of the new folder and navigate': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton, `${page.elements.gridItem}--0`)
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name).before(browser.globals.waitForConditionTimeout);
    },
    'navigate back and go in with a click': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-index__parent-folder')
            .click('.sw-media-index__parent-folder')
            .expect.element(`${page.elements.gridItem}--0 .sw-media-base-item__name`).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name).before(browser.globals.waitForConditionTimeout);
        browser
            .click('.sw-media-folder-item')
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name).before(browser.globals.waitForConditionTimeout);
    },
    'upload image to folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'navigate back and check if the image is assigned correctly': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.icon--folder-breadcrumbs-back-to-root')
            .click('.icon--folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'check if the folder can be found in other modules': (browser) => {
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1
            })
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-sidebar-navigation-item')
            .click('.sw-sidebar-navigation-item')
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name).before(browser.globals.waitForConditionTimeout);
    },
    after: (browser) => {
        browser.end();
    }
};
