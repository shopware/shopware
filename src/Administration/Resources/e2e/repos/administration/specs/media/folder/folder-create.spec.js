const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-create', 'create', 'upload'],
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'create new folder': (browser) => {
        const page = mediaPage(browser);
        page.createFolder(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'verify creation of and navigate into new folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: page.elements.showMediaAction
            })
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'upload image to folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
    },
    'navigate back and check if the image is assigned correctly': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.icon--multicolor-folder-breadcrumbs-back-to-root')
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
            .expect.element('.sw-sidebar-media-item__content').to.have.text.that.contains(global.MediaFixtureService.mediaFolderFixture.name);
    }
};
