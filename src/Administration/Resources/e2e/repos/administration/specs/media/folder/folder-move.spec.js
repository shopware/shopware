const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-move', 'move'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture({
            name: 'First folder'
        }).then(() => {
            return global.MediaFixtureService.setFolderFixture({
                name: 'Second folder'
            });
        }).then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'verify creation of the new folders and navigate to the first one': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton, `${page.elements.gridItem}--0 `)
            .expect.element('.smart-bar__header').to.have.text.that.equals('First folder');
    },
    'upload image to folder and verify placement in folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);

        browser
            .assert.containsText(page.elements.mediaNameLabel, 'sw-login-background.png')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('First folder');
    },
    'navigate back to root folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'upload an image': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`);

        browser
            .assert.containsText(page.elements.mediaNameLabel, 'sw-test-image.png');
    },
    'move image to second folder': (browser) => {
        const page = mediaPage(browser);

        page.moveMediaItem('sw-test-image.png', 'media', 2);
    },
    'move first folder to second one': (browser) => {
        const page = mediaPage(browser);

        page.moveMediaItem('First folder', 'folder');
    },
    'verify movement': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton, `${page.elements.gridItem}--0 `)
            .expect.element('.smart-bar__header').to.have.text.that.equals('Second folder');

        browser.assert.containsText(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`, 'First folder')
            .assert.containsText(`${page.elements.gridItem}--1 ${page.elements.baseItemName}`, 'sw-test-image.png');

        browser
            .clickContextMenuItem(page.elements.showMediaAction, page.elements.contextMenuButton, `${page.elements.gridItem}--0 `)
            .expect.element('.smart-bar__header').to.have.text.that.equals('First folder');

        browser.expect.element(`${page.elements.gridItem}--0 ${page.elements.baseItemName}`).to.have.text.that.equals('sw-login-background.png');
    }
};
