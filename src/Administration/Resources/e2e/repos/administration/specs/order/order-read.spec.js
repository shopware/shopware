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

        console.log('timeout ', browser.waitForConditionTimeout);
        browser
            .openMainMenuEntry('#/sw/order/index', 'Orders')
            .waitForElementVisible('.smart-bar__actions')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(`${global.OrderFixtureService.customerStorefrontFixture.firstName} ${global.OrderFixtureService.customerStorefrontFixture.lastName}`).before(5000);
    },
    'open existing order': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem('.sw-order-list__order-view-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail');
    },
    'verify customer details': (browser) => {
        const page = orderPage(browser);

        browser
            .expect.element(`${page.elements.userMetadata}-user-name`).to.have.text.that.equals(`${global.OrderFixtureService.customerStorefrontFixture.firstName} ${global.OrderFixtureService.customerStorefrontFixture.lastName}`).before(5000);
        browser.expect.element(`${page.elements.userMetadata}-item`).to.have.text.that.contains(global.OrderFixtureService.customerStorefrontFixture.email).before(5000);
        browser.expect.element('.sw-order-detail-base__user-summary-data').to.have.text.that.contains(global.ProductFixtureService.productFixture.price.gross).before(5000);
        browser.expect.element('.sw-order-base__label-sales-channel').to.have.text.that.contains('Storefront API').before(5000);
    },
    'verify line item details': (browser) => {
        const page = orderPage(browser);

        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.ProductFixtureService.productFixture.name).before(5000);
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.ProductFixtureService.productFixture.price.gross).before(5000);
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('19 %').before(5000);
    },
    'verify delivery metadata': (browser) => {
        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .expect.element('.sw-address__headline').to.have.text.that.equals('Shipping address').before(5000);
        browser.expect.element('.sw-address__location').to.have.text.that.equals('33602 Bielefeld').before(5000);
    },
    'open line item\'s product': (browser) => {
        const page = orderPage(browser);

        browser
            .getLocationInView('.sw-order-detail-base__summary')
            .clickContextMenuItem('.sw-context-menu__content', page.elements.contextMenuButton)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.ProductFixtureService.productFixture.name).before(5000);
    },
    after: (browser) => {
        browser.end();
    }
};
