const productPage = require('administration/page-objects/module/sw-product.page-object.js');

module.exports = {
    '@tags': ['product', 'product-edit', 'variant'],
    '@disabled': !global.flags.isActive('next2021'),
    before: (browser, done) => {
        return global.ProductFixtureService.setProductFixture().then(() => {
            return global.PropertyFixtureService.setPropertyFixture({
                options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
            });
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
    'navigate to variant generator listing and start': (browser) => {
        const page = productPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-product-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementVisible('.sw-product-detail__tab-variants')
            .click('.sw-product-detail__tab-variants')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible('.sw-product-detail-variants__generated-variants__empty-state')
            .click(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .waitForElementVisible('.sw-product-modal-variant-generation');
    },
    'create one-dimensional variant': (browser) => {
        const page = productPage(browser);

        page.generateVariants('Color', [0, 1, 2]);
        browser.waitForElementVisible('.sw-product-variants-overview');
    },
    'verify variant generation': (browser) => {
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('Red');
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('Yellow');
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('Green');
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('.1');
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('.2');
        browser.expect.element('.sw-data-grid__body').to.have.text.that.contains('.3');
    },
    'generate variants anew, with changed prizes and restrictions': (browser) => {
        const page = productPage(browser);
        const priceInputSelector =
            '.sw-data-grid__row--0 td:nth-of-type(3) .sw-product-variants-price-field__input:nth-of-type(1) input';

        browser
            .click('.sw-product-variants__generate-action')
            .waitForElementVisible(page.elements.modal)
            .click('.sw-variant-modal__surcharge-configuration')
            .waitForElementVisible('.sw-product-variants-configurator-prices')
            .click('.sw-product-variants-configurator-prices__groupElement')
            .fillField(priceInputSelector, '10')
            .click('.sw-variant-modal__restriction-configuration')
            .waitForElementVisible('.sw-product-variants-configurator-restrictions')
            .click('.sw-product-variants-configurator-restrictions .sw-button--ghost')
            .waitForElementVisible('.sw-product-variants-configurator-restrictions__modal-main')
            .fillSelectField('select[name=sw-field--selectedGroup', 'Color')
            .click('.sw-multi-select')
            .waitForElementVisible('.sw-multi-select__results')
            .click('.sw-multi-select-option--0')
            .waitForElementVisible('.sw-multi-select__selection-item-holder')
            .click(`.sw-product-variants-configurator-restrictions__modal .sw-modal__footer ${page.elements.primaryButton}`)
            .waitForElementNotPresent('.sw-product-variants-configurator-restrictions__modal-main')
            .click(`.sw-modal__footer ${page.elements.primaryButton}`)
            .waitForElementNotPresent('.sw-product-modal-variant-generation');
    }
};
