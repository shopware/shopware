let currentProduct = '';

module.exports = {
    '@tags': ['checkout', 'checkout-basic'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            return global.ProductFixtureService.setProductFixture();
        }).then((result) => {
            return global.ProductFixtureService.setProductVisible(result);
        }).then(() => {
            return global.ProductFixtureService.search('product', {
                value: global.ProductFixtureService.productFixture.name
            });
        })
            .then((data) => {
                currentProduct = data;
                done();
            });
    },
    'find product': (browser) => {
        browser
            .url(process.env.APP_URL)
            .waitForElementVisible('input[name=search]')
            .setValue('input[name=search]', currentProduct.attributes.name)
            .expect.element('.result-product .result-link').to.have.text.that.contains(currentProduct.attributes.name);

        browser
            .click('.result-product .result-link')
            .waitForElementVisible('.product-detail-content')
            .assert.containsText('.product-detail-name', currentProduct.attributes.name)
            .assert.containsText('.product-detail-price', currentProduct.attributes.price.gross)
            .assert.containsText('.product-detail-ordernumber', currentProduct.attributes.productNumber);
    },
    'add product to card': (browser) => {
        browser
            .click('.buy-widget-submit')
            .waitForElementVisible('.js-off-canvas.is-open');
    },
    'check off-canvas cart and continue': (browser) => {
        browser
            .assert.containsText('.cart-item-link-name', currentProduct.attributes.name)
            .assert.containsText('.cart-item-link-price', currentProduct.attributes.price.gross)
            .assert.containsText('.cart-prices-subtotal', currentProduct.attributes.price.gross)
            .click('.cart-actions .btn-light');
    },
    'check card widget': (browser) => {
        browser
            .waitForElementVisible('.cart-widget')
            .assert.containsText('.cart-widget-total', currentProduct.attributes.price.gross)
            .assert.containsText('.cart-widget-badge', '1');
    },
    'check checkout page and continue': (browser) => {
        browser
            .waitForElementVisible('.card-body')
            .assert.containsText('.cart-item-label', currentProduct.attributes.name)
            .assert.containsText('.cart-item-unit-price', currentProduct.attributes.price.gross)
            .assert.containsText('.checkout-summary-value', currentProduct.attributes.price.gross)
            .click('.checkout-sidebar .btn-primary');
    },
    'log in customer': (browser) => {
        browser
            .waitForElementVisible('.checkout.is-register')
            .click('.login-collapse-toggle')
            .waitForElementVisible('.login-form')
            .fillField('#loginMail', 'test@example.com')
            .fillField('#loginPassword', 'shopware')
            .click('.login-submit .btn-primary');
    },
    'check checkout finish page': (browser) => {
        browser.expect.element('.card-title').to.have.text.that.contains('Terms, conditions and cancellation policy');

        browser
            .waitForElementVisible('.confirm-address')
            .assert.containsText('.confirm-address', 'Pep Eroni')
            .getLocationInView('.checkout-main')
            .waitForElementVisible('.checkout-main')
            .assert.containsText('.cart-item-label', currentProduct.attributes.name)
            .assert.containsText('.cart-item-total-price', currentProduct.attributes.price.gross)
            .assert.containsText('.checkout-summary-item:nth-of-type(1) .checkout-summary-value', currentProduct.attributes.price.gross)
            .assert.containsText('.checkout-summary-total .checkout-summary-value', currentProduct.attributes.price.gross)
            .click('#confirmFormSubmit');
    },
    'finish order': (browser) => {
        browser
            .getLocationInView('.confirm-terms label')
            .moveToElement('.confirm-terms label', 1, 1).mouseButtonClick('left')
            .getLocationInView('#confirmFormSubmit')
            .click('#confirmFormSubmit')
            .waitForElementVisible('.nav-main');
    },
    after: (browser) => {
        browser.end();
    }
};
