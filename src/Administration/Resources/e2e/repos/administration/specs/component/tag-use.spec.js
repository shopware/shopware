const productPage = require('administration/page-objects/module/sw-product.page-object.js');

module.exports = {
    '@tags': ['component', 'tag', 'tag-usage', 'product'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture().then(() => {
            return global.AdminFixtureService.create('tag');
        }).then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-product'
            })
            .expect.element(page.elements.productListName).to.have.text.that.contains(global.ProductFixtureService.productFixture.name);
    },
    'find product to be edited': (browser) => {
        const page = productPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-entity-listing__context-menu-edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementNotPresent(`.product-basic-form ${page.elements.loader}`)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.ProductFixtureService.productFixture.name);
    },
    'find tag component': (browser) => {
        browser
            .getLocationInView('.sw-product-category-form')
            .waitForElementVisible('.sw-tag-field');
    },
    'add existing tag': (browser) => {
        browser.fillSwSelect('.sw-tag-field', { value: 'Schöner Tag', isMulti: true });
        browser.clearValue('.sw-tag-field .sw-multi-select__selection-item-input input');
    },
    'create new tag': (browser) => {
        const page = productPage(browser);

        page.createProductTag('What does it means[TM]???', 1);
    },
    'save product with tags': (browser) => {
        const page = productPage(browser);

        browser
            .click(page.elements.productSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    },
    'remove second tag': (browser) => {
        const page = productPage(browser);

        browser
            .refresh()
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.ProductFixtureService.productFixture.name);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .getLocationInView('.sw-product-category-form')
            .waitForElementVisible('.sw-tag-field .sw-multi-select__selection-item-input')
            .click('.sw-tag-field .sw-multi-select__selection-item-input')
            .setValue('.sw-tag-field .sw-multi-select__selection-item-input input', browser.Keys.BACK_SPACE)
            .waitForElementNotPresent('.sw-tag-field .sw-multi-select__selection-item-holder--1 .sw-multi-select__selection-item')
            .expect.element('.sw-tag-field .sw-multi-select__selection-item-holder--0 .sw-multi-select__selection-item').to.not.have.text.that.equals('What does it means[TM]???');
    },
    'save product with tags once more': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementNotPresent('.sw-product-detail__save-action .icon--small-default-checkmark-line-medium')
            .click(page.elements.productSaveAction)
            .waitForElementVisible('.sw-product-detail__save-action .icon--small-default-checkmark-line-medium');
    },
    'check truncated tag': (browser) => {
        const page = productPage(browser);

        page.createProductTag('Lorem ipsum dolor sit amet consetetur sadipscing elitr sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat sed diam voluptua At vero eos et accusam', 1);
        browser.expect.element('.sw-tag-field .sw-multi-select__selection-item-holder--1 .sw-multi-select__selection-item').to.have.css('text-overflow').which.equals('ellipsis');
    }
};
