const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'folder', 'folder-dissolve', 'dissolve'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture().then(() => {
            done();
        });
    },
    'open media listing': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
    },
    'verify creation of the new folder and navigate': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: page.elements.showMediaAction,
                scope: `${page.elements.gridItem}--0 `
            })
            .expect.element('.smart-bar__header').to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'upload image to folder and verify placement in folder': (browser) => {
        const page = mediaPage(browser);
        page.uploadImageViaURL(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);

        browser
            .assert.containsText(page.elements.mediaNameLabel, 'sw-login-background.png')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.MediaFixtureService.mediaFolderFixture.name);
    },
    'navigate back to root folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .click('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.icon--multicolor-folder-breadcrumbs-back-to-root')
            .waitForElementNotPresent(page.elements.previewItem);
    },
    'dissolve folder': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-media-context-item__dissolve-folder-action',
                scope: `${page.elements.gridItem}--0`
            })
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to dissolve "${global.MediaFixtureService.mediaFolderFixture.name}" ?`);

        browser
            .click('.sw-media-modal-folder-dissolve__confirm')
            .checkNotification(`Folder "${global.MediaFixtureService.mediaFolderFixture.name}" has been dissolved successfully`);
    },
    'verify if folder was removed and images persisted': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementPresent(page.elements.previewItem)
            .waitForElementVisible(page.elements.mediaNameLabel);
        browser.expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-login-background.png');
    }
};
