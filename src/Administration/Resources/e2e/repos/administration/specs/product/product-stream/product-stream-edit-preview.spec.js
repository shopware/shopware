const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream-preview', 'preview', 'edit'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.AdminFixtureService.create('product-stream').then(() => {
            return global.ProductFixtureService.setProductFixture().then(() => {
                done();
            });
        }).then(() => {
            done();
        });
    },
    'navigate to product stream module and look for product stream to be edited': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-product-stream'
            })
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'open product stream details and change the given data': (browser) => {
        const page = productStreamPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_product_stream_list__edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('1st product stream');

        browser
            .fillField('input[name=sw-field--productStream-name]', 'Edited product stream', true)
            .fillField('textarea[name=sw-field--productStream-description]', 'The product stream was edited by an e2e test', true)
            .click(page.elements.streamSaveAction)
            .checkNotification('The product stream "Edited product stream" has been saved successfully.');
    },
    'create simple product filter': (browser) => {
        const page = productStreamPage(browser);

        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Equals any',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: true
        });
    },
    'open preview and view filter in it': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click('.sw-product-stream-detail__open_modal_preview')
            .expect.element('.sw-modal__title').to.have.text.that.equals('Preview');
        browser.expect.element('.sw-product-stream-modal-preview__column-product-name').to.have.text.that.equals('Product name');

        browser.click('.sw-product-stream-modal-preview__close-action')
            .waitForElementNotPresent(page.elements.modal);
    },
    'change the filter': (browser) => {
        const page = productStreamPage(browser);

        browser.setValue('.sw-select--multi', browser.Keys.BACK_SPACE);

        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Not equals',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: false
        });
    },
    'open preview once more and verify change': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click('.sw-product-stream-detail__open_modal_preview')
            .expect.element('.sw-modal__title').to.have.text.that.equals('Preview');

        browser
            .waitForElementPresent('.sw-empty-state')
            .expect.element('.sw-product-stream-modal-preview__content').to.not.have.text.that.contains('Product name');

        browser
            .click('.sw-product-stream-modal-preview__close-action')
            .waitForElementNotPresent(page.elements.modal);
    }
};
