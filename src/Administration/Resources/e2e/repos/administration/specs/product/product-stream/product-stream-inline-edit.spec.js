const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product-stream-inline-edit', 'product-stream', 'inline-edit'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.AdminFixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .waitForElementVisible('.sw-button__content');
    },
    'inline edit product stream name and description and verify edits': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .expect.element(`${page.elements.gridRow}--0 .sw-product-stream-list__column-name`).to.have.text.that.equals('1st product stream');

        browser
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .waitForElementVisible('.is--inline-editing')
            .fillField('input[name=sw-field--item-name]', 'Stream it', true)
            .waitForElementVisible(page.elements.gridRowInlineEdit)
            .click(page.elements.gridRowInlineEdit)
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left', 'Stream it')
            .moveToElement(`${page.elements.gridRow}--0 .sw-grid-column:nth-child(3)`, 5, 5).doubleClick()
            .waitForElementVisible(page.elements.gridRowInlineEdit)
            .fillField('input[name=sw-field--item-description]', 'Edit the first stream', true)
            .click(page.elements.gridRowInlineEdit)
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left:nth-child(2)', 'Stream it');
    },
    after: (browser) => {
        browser.end();
    }
};
