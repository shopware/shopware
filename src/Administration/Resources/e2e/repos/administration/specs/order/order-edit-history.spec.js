const orderPage = require('administration/page-objects/module/sw-order.page-object.js');

module.exports = {
    '@tags': ['order', 'order-edit-history', 'edit', 'order-state', 'order-history'],
    '@disabled': !global.flags.isActive('next1567'),
    before: (browser, done) => {
        return global.ProductFixtureService.setProductFixture().then((result) => {
            return global.OrderFixtureService.createGuestOrder(result);
        }).then(() => {
            return global.OrderFixtureService.search('salutation', {
                identifier: 'displayName',
                value: 'Mr.'
            });
        }).then(() => {
            done();
        });
    },
    'find order and check its open state': (browser) => {
        const page = orderPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/order/index',
                mainMenuId: 'sw-order'
            });
        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains(`${global.OrderFixtureService.customerStorefrontFixture.firstName} ${global.OrderFixtureService.customerStorefrontFixture.lastName}`);
        browser.expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Open');
    },
    'open existing order and find history card': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-order-list__order-view-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail')
            .waitForElementPresent('.sw-order-delivery-metadata')
            .getLocationInView('.sw-order-delivery-metadata');
    },
    'check current order and payment status history': (browser) => {
        const page = orderPage(browser);

        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'payment',
            signal: 'neutral'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral'
        });
    },
    'set order status to \"Cancelled\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Cancelled',
            type: 'order',
            signal: 'danger',
            position: 1
        });
    },
    'set payment status to \"Reminded\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Reminded',
            type: 'payment',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'progress',
            position: 1
        });
    },
    'set order status to \"Open\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'order',
            position: 2
        });
    },
    'set order status to \"In progess\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'In progress',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'In progress',
            type: 'order',
            signal: 'progress',
            position: 3
        });
    },
    'set order status to \"Done\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Done',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Done',
            type: 'order',
            signal: 'success',
            position: 4
        });
    },
    'check tooltip info of completed order status': (browser) => {
        const page = orderPage(browser);

        browser
            .waitForElementVisible('.sw-order-state-history-card__order-state .sw-order-state-history__entry--4 .sw-order-state-card__date', 30, () => {
                browser.moveToElement('.sw-order-state-history-card__order-state .sw-order-state-history__entry--4 .sw-order-state-card__date', 2, 2, () => {
                    browser
                        .waitForElementVisible('.sw-tooltip')
                        .useXpath()
                        .waitForElementVisible('//*[contains(text(), "Last edited by admin")]')
                        .useCss();
                });
            }, 'Find order timestamp info')
            .moveToElement(page.elements.smartBarHeader, 5, 5);
    },
    'set payment status to \"Paid\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Paid',
            type: 'payment',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Paid',
            type: 'payment',
            signal: 'success',
            position: 2
        });
    },
    'verify order completion in listing': (browser) => {
        const page = orderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Done');
    }
};
