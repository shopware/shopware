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
                mainMenuId: 'sw-product'
            })
            .expect.element(page.elements.productListName).to.have.text.that.contains(global.ProductFixtureService.productFixture.name);
    },
    'find product to be translated': (browser) => {
        const page = productPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-product-list__edit-action',
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
        browser
            .fillSwSelectComponent(
                '.sw-tag-field .sw-select--multi',
                {
                    value: 'Schöner Tag',
                    isMulti: true,
                    searchTerm: 'Schöner'
                }
            );
    },
    'create new tag': (browser) => {
        const page = productPage(browser);

        page.createProductTag('What does it means[TM]???', 1);
    },
    'save product with tags': (browser) => {
        const page = productPage(browser);

        browser
            .click(page.elements.productSaveAction)
            .checkNotification('Product "Product name" has been saved successfully.');
    },
    'remove second tag': (browser) => {
        const page = productPage(browser);

        browser
            .getLocationInView('.sw-product-category-form')
            .setValue(`.sw-tag-field ${page.elements.selectInput}`, browser.Keys.BACK_SPACE)
            .waitForElementNotPresent(`${page.elements.selectSelectedItem}--1`)
            .expect.element(`${page.elements.selectSelectedItem}--0`).to.not.have.text.that.equals('What does it means[TM]???');
    },
    'save product with tags once more': (browser) => {
        const page = productPage(browser);

        browser
            .click(page.elements.productSaveAction)
            .checkNotification('Product "Product name" has been saved successfully.');
    },
    'check truncated tag': (browser) => {
        const page = productPage(browser);

        page.createProductTag('Lorem ipsum dolor sit amet consetetur sadipscing elitr sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat sed diam voluptua At vero eos et accusam', 1);
        browser.expect.element(`${page.elements.selectSelectedItem}--1 .sw-select__selection-item`).to.have.css('text-overflow').which.equals('ellipsis');
    }
};
