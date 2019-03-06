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
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton, `${page.elements.gridItem}--0`)
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back and go in with a click': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.sw-media-library__parent-folder')
            .expect.element(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
        browser
            .click('.sw-media-folder-item')
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'upload image to folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'navigate back and check if the image is assigned correctly': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'check if the folder can be found in other modules': (browser) => {
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .click('.smart-bar__actions a[href="#/sw/product/create"]')
            .click('.sw-sidebar-navigation-item')
            .expect.element(page.elements.folderNameLabel).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
