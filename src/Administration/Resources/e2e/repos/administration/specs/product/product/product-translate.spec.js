const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: "Really good product",
    descriptionLong: "This describes a product. It is your product. You will take care of your product. You will set a price, keep records of storage quantities and take care of whatever needs your product might develop. You love your product. Your are the product. Now go find someone dumb enough to buy your precious product.",
};

module.exports = {
    '@tags': ['product', 'product-translate', 'translation', 'language-switch'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixtures(fixture).then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    'find product to be translated': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .waitForElementVisible(page.elements.contextMenu)
            .click('.sw-context-menu-item__text')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(fixture.name);
    },
    'change language to german': (browser) => {
        const page = productPage(browser);
        browser
            .waitForElementVisible('.sw-language-switch')
            .click('.sw-language-switch')
            .waitForElementNotPresent('.sw-field__select-load-placeholder');

        browser.expect.element('.sw-select-option:last-child').to.have.text.that.equals('Deutsch').before(5000);
        browser
            .click('.sw-select-option:last-child')
            .waitForElementNotPresent('.sw-field__select-load-placeholder')
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal}__body`, 'There are unsaved changes in the current language. Do you want to save them now?')
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .checkNotification(`Product "${fixture.name}" has been saved successfully.`)
            .waitForElementVisible('.sw-language-info')
            .expect.element('.sw-language-info').to.have.text.that.equals(`Translation of "${fixture.name}" in the root language "Deutsch". Fallback is the system default language "English".`);

    },
    'translate some information to german': (browser) => {
        const page = productPage(browser);

        browser
            .fillField('input[name=sw-field--product-name]', 'Echt gutes Produkt', true)
            .fillField('.ql-editor', 'Siehst du nicht, dass das ein wunderbares Produkt ist?', true, 'editor')
            .click(page.elements.productSaveAction)
            .checkNotification('Product "Echt gutes Produkt" has been saved successfully.');
    },
    'verify product in listing': (browser) => {
        const page = productPage(browser);

        browser
            .click('.smart-bar__actions .sw-button__content')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .expect.element(`${page.elements.gridRow}--0 .sw-product-list__column-product-name`).to.have.text.that.equals('Echt gutes Produkt');
    },
    'change back to english and verify again': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementVisible('.sw-language-switch')
            .click('.sw-language-switch')
            .waitForElementNotPresent('.sw-field__select-load-placeholder')
            .expect.element('.sw-select-option:first-child').to.have.text.that.equals('English').before(5000);

        browser
            .click('.sw-select-option:first-child')
            .waitForElementNotPresent('.sw-field__select-load-placeholder')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(`${page.elements.gridRow}--0  .sw-product-list__column-product-name`).to.have.text.that.equals(fixture.name).before(5000);
    },
    after: (browser) => {
        browser.end();
    }
};
