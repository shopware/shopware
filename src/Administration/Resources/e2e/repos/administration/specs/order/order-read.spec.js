const orderPage = require('administration/page-objects/module/sw-order.page-object.js');

module.exports = {
    '@tags': ['order', 'order-read', 'read'],
    before: (browser, done) => {
        return global.ProductFixtureService.setProductFixture().then((result) => {
            return global.OrderFixtureService.createGuestOrder(result);
        }).then(() => {
            done();
        });
    },
    'open order module and find order': (browser) => {
        const page = orderPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/order/index',
                mainMenuId: 'sw-order'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains(`${global.OrderFixtureService.customerStorefrontFixture.firstName} ${global.OrderFixtureService.customerStorefrontFixture.lastName}`);
    },
    'open existing order': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem('.sw-order-list__order-view-action', page.elements.contextMenuButton, `${page.elements.dataGridRow}--0`)
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail');
    },
    'verify customer details': (browser) => {
        const page = orderPage(browser);

        browser
            .expect.element(`${page.elements.userMetadata}-user-name`).to.have.text.that.equals(`${global.OrderFixtureService.customerStorefrontFixture.firstName} ${global.OrderFixtureService.customerStorefrontFixture.lastName}`);
        browser.expect.element(`${page.elements.userMetadata}-item`).to.have.text.that.contains(global.OrderFixtureService.customerStorefrontFixture.email);
        browser.expect.element('.sw-order-detail-base__user-summary-data').to.have.text.that.contains(global.ProductFixtureService.productFixture.price.gross);
        browser.expect.element('.sw-order-base__label-sales-channel').to.have.text.that.contains('Storefront API');
    },
    'verify line item details': (browser) => {
        const page = orderPage(browser);

        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.ProductFixtureService.productFixture.name);
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.ProductFixtureService.productFixture.price.gross);
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('19 %');
    },
    'verify delivery metadata': (browser) => {
        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .expect.element('.sw-address__headline').to.have.text.that.equals('Shipping address');
        browser.expect.element('.sw-order-delivery-metadata .sw-address__location').to.have.text.that.equals('33602 Bielefeld');
    },
    'open line item\'s product': (browser) => {
        const page = orderPage(browser);

        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .clickContextMenuItem('.sw-context-menu__content', page.elements.contextMenuButton)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.ProductFixtureService.productFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
