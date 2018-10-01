module.exports = {
    'open catalog overview': (browser) => {
        browser
        // open catalog overview
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-catalog span.collapsible-text', 'Catalogues')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/catalog/index"]')
            .waitForElementVisible('.smart-bar__actions a');
    },
    'create second catalog': (browser) => {
        browser
        //  open create catalog and fill in
            .click('a[href="#/sw/catalog/index"]')
            .waitForElementVisible('a[href="#/sw/catalog/create"]')
            .click('a[href="#/sw/catalog/create"]')
            .waitForElementVisible('.sw-catalog-detail')
            .assert.urlContains('#/sw/catalog/create')
            .assert.containsText('.sw-card__title', 'Properties')
            .setValue('input[name=catalogName]', 'Test Catalog')
            .click('button.sw-button--primary')
            .waitForElementVisible('.sw-card-view');
    },
    'open media index and change catalog': (browser) => {
        browser
        //  open media catalog
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-media span.collapsible-text', 'Media')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]')
            .waitForElementVisible('div.sw-media-grid.sw-media-index__catalog-grid')
            .moveToElement('.sw-media-index__catalog-grid .sw-media-grid__content-cell', 5, 5)
            .pause(100)
            .click('.sw-media-index__catalog-grid .sw-media-grid__content-cell')
            .waitForElementVisible('div.sw-page.sw-media-catalog')
        // change catalog
            .waitForElementVisible('.sw-media-catalog__change')
            .assert.containsText('.sw-media-catalog__button', 'Default catalogue')
            .click('.sw-media-catalog__change')
            .waitForElementVisible('.sw-context-menu')
            .waitForElementVisible('.sw-context-menu-item')
            .assert.containsText('.sw-context-menu-item', 'Test Catalog')
            .click('.sw-context-menu-item')
            .assert.containsText('.sw-media-catalog__button', 'Test Catalog')
        // change back to default catalog
            .click('.sw-media-catalog__change')
            .waitForElementVisible('.sw-context-menu')
            .waitForElementVisible('.sw-context-menu-item')
            .click('.sw-context-menu-item')
            .waitForElementVisible('.sw-media-catalog')
            .waitForElementVisible('.sw-media-upload');
    },
    'create items and check position': (browser) => {
        browser
        //  create first item
            .click('.sw-media-upload__context-button')
            .waitForElementVisible('.sw-context-menu')
            .waitForElementVisible('.sw-media-upload__url')
            .click('.sw-media-upload__url')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .setValue('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/img/sw-media-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close')
        // pause to wait until notification is vanished
            .pause(1000)
        // create second item
            .click('.sw-media-upload__context-button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-media-upload__url')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .setValue('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/img/sw-media-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close')
        // pause to wait until notification is vanished
            .pause(1000)
        // check nothing uploaded in second catalog
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]')
            .waitForElementVisible('.sw-media-index__catalog-grid')
            .click('.sw-media-index__catalog-grid .sw-media-grid__content-cell:nth-of-type(2)')
            .waitForElementVisible('.sw-empty-state')
        // check for two items in media index and default catalog
            .click('a.sw-admin-menu__navigation-link[href="#/sw/media/index"]')
            .waitForElementVisible('.sw-media-grid__content-cell:nth-of-type(2)')
            .click('.sw-media-index__catalog-grid .sw-media-grid__content-cell:nth-of-type(1)')
            .waitForElementVisible('.sw-media-catalog')
            .waitForElementVisible('.sw-media-grid-media-item:nth-of-type(2)');
    },
    'check item metadata and delete item': (browser) => {
        browser
        // open sidebar
            .click('.sw-media-grid-item')
            .waitForElementVisible('.sw-sidebar-item__content')
        // check metadata
            .assert.containsText('.sw-media-quickinfo-metadata-name', 'Name:')
            .assert.containsText('.sw-media-quickinfo-metadata-name', 'sw-media-background')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'MIME-Type:')
            .assert.containsText('.sw-media-quickinfo-metadata-mimeType', 'image/png')
            .assert.containsText('.sw-media-quickinfo-metadata-size', 'Size:')
            .assert.containsText('.sw-media-quickinfo-metadata-size', '1.01KB')
            .assert.containsText('.sw-media-quickinfo-metadata-createdAt', 'Uploaded at:')
            .assert.containsText('.sw-media-quickinfo-metadata-url', 'URL:')
            .assert.containsText('.sw-media-quickinfo-metadata-width', 'Width:')
            .assert.containsText('.sw-media-quickinfo-metadata-width', '16px')
            .assert.containsText('.sw-media-quickinfo-metadata-height', 'Height:')
            .assert.containsText('.sw-media-quickinfo-metadata-height', '16px')
        // check delete item
            .click('li.quickaction--delete')
            .waitForElementVisible('div.sw-modal.sw-modal--small.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-media-background" ?')
            .waitForElementVisible('.sw-modal__footer .sw-media-modal-delete__confirm')
            .click('.sw-media-modal-delete__confirm')
        // pause to wait until notification is vanished
            .pause(1000)
        // delete second item and verifiy deletion
            .click('.sw-context-button__button')
            .waitForElementVisible('.sw-context-menu__content')
            .click('.sw-context-menu__group .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-media-modal-delete')
            .assert.containsText('.sw-modal__body', 'Do you want to delete "sw-media-background" ?')
            .click('.sw-media-modal-delete__confirm')
            .waitForElementVisible('.sw-empty-state')

            .end();
    }
};
