const orderPage = require('administration/page-objects/module/sw-order.page-object.js');

module.exports = {
    '@tags': ['order', 'order-edit-state', 'edit', 'state', 'order-state'],
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
        browser.expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Open');
    },
    'open existing order': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-order-list__order-view-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail');
    },
    'change payment state to \"reminded\"': (browser) => {
        const page = orderPage(browser);

        browser
            .waitForElementNotPresent('.sw-loader__element');

        page.setOrderState({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'progress'
        });
    },
    'change order state to \"Cancelled\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            signal: 'danger'
        });
    },
    'verify cancelled order state in listing': (browser) => {
        const page = orderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Cancelled');
    },
    'open order again (literally)': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-order-list__order-view-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail');

        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral'
        });
    },
    'check that status \"Done\" is disabled': (browser) => {
        const page = orderPage(browser);
        const orderStateSelect = '.sw-order-state-select__order-state select[name=sw-field--selectedActionName]';

        browser
            .click(orderStateSelect)
            .expect.element(`${orderStateSelect} option:nth-of-type(3)`).to.not.be.enabled;
        browser.click(page.elements.smartBarHeader);
    },
    'verify reopened order in listing': (browser) => {
        const page = orderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Open');
    },
    'change order state to \"In progress\", then \"Complete\"': (browser) => {
        const page = orderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-order-list__order-view-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .waitForElementVisible('.sw-order-detail')
            .assert.urlContains('#/sw/order/detail');

        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral'
        });
        page.setOrderState({
            stateTitle: 'In progress',
            type: 'order',
            signal: 'progress'
        });

        browser.refresh();

        page.setOrderState({
            stateTitle: 'Done',
            type: 'order',
            signal: 'success'
        });
    },
    'change payment state to \"Paid\"': (browser) => {
        const page = orderPage(browser);

        page.setOrderState({
            stateTitle: 'Paid',
            type: 'payment',
            signal: 'success'
        });
    },
    'verify completed order state in listing': (browser) => {
        const page = orderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`).to.have.text.that.contains('Done');
    }
};
