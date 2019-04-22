const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream-edit-filter', 'product-stream', 'edit', 'filter'],
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
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
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
    'create first filter for active state': (browser) => {
        const page = productStreamPage(browser);

        page.createBasicSelectCondition({
            type: 'Active',
            ruleSelector: `${page.elements.baseCondition}`,
            value: 'Yes'
        });
    },
    'create and-filter for products before active-filter': (browser) => {
        const page = productStreamPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-condition-base__create-before-action',
                scope: `${page.elements.conditionAndContainer}--0`
            });

        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Is equal to any of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: true
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--0 .sw-select__single-selection`).to.have.text.that.equals('Product');
    },
    'create and-filter for prices after active filter': (browser) => {
        const page = productStreamPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-condition-base__create-after-action',
                scope: `${page.elements.conditionAndContainer}--1`
            });

        page.createCombinedInputSelectCondition({
            type: 'Price',
            secondValue: '100',
            firstValue: 'Gross',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            isMulti: false,
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2 ${page.elements.baseCondition}`
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--2 .field--condition:nth-of-type(1)`).to.have.text.that.equals('Price');
    },
    'create a subcondition with release date': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click('.sw-condition-or-container__actions--or')
            .waitForElementVisible(page.elements.orSpacer);

        page.createBasicInputCondition({
            type: 'Release date',
            inputName: 'sw-field--filterValue',
            operator: 'Is equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--0`,
            value: '14.05.2019'
        });
    },
    'create another subcondition with stock condition': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-and-container__actions--sub`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.orSpacer}`);

        page.createBasicInputCondition({
            type: 'Stock',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1`,
            value: '10'
        });
    },
    'create or-filter in this subcondition with sale unit': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 .sw-condition-or-container__actions--or`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.orSpacer}`);

        page.createBasicInputCondition({
            type: 'Stock',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 ${page.elements.conditionOrContainer}--1`,
            value: '10'
        });
    },
    'delete this rule': (browser) => {
        const page = productStreamPage(browser);

        browser
            .getLocationInView(
                '.sw-product-stream-detail__condition_container'
            )
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 ${page.elements.conditionOrContainer}--1`
            })
            .waitForElementNotPresent(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`);
    },
    'delete the first subcondition': (browser) => {
        const page = productStreamPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 button.sw-button.sw-condition-and-container__actions--delete`)
            .waitForElementNotPresent(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`);
    },
    'delete all containers': (browser) => {
        browser.click('.sw-condition-and-container__actions--delete');
    }
};
