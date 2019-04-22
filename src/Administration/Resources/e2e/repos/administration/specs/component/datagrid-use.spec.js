const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['component', 'datagrid', 'datagrid-usage', 'customer', 'inline-edit'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            return global.CustomerFixtureService.setCustomerFixture({
                firstName: 'Zapp',
                lastName: 'Zarapp',
                customerNumber: 'Z-1234'
            });
        }).then(() => {
            done();
        });
    },
    'open customer listing (which uses datagrid)': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            });
    },
    'toggle compact mode': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible('.is--compact')
            .clickContextMenuItem('.sw-data-grid-settings__trigger', {
                scope: `${page.elements.dataGridHeader} .sw-data-grid__row`
            })
            .tickCheckbox('input[name=sw-field--currentCompact]', false)
            .waitForElementNotPresent('.is--compact')
            .tickCheckbox('input[name=sw-field--currentCompact]', true)
            .waitForElementVisible('.is--compact');

    },
    'check that name column cannot be hidden': (browser) => {
        browser.expect.element('.sw-data-grid__settings-item--1').to.have.text.that.contains('Name');
        browser.expect.element('.sw-data-grid__settings-item--1 input').to.not.be.enabled;
    },
    'hide and retrieve mail column': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-page__head-area')
            .expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--5`).to.have.text.that.contains('Email');

        browser
            .clickContextMenuItem(`${page.elements.dataGridColumn}--5 .sw-context-button__button`, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridHeader} .sw-data-grid__row`
            })
            .waitForElementNotVisible(`${page.elements.dataGridColumn}--5`)
            .clickContextMenuItem('.sw-data-grid-settings__trigger', {
                scope: `${page.elements.dataGridHeader} .sw-data-grid__row`
            })
            .tickCheckbox('.sw-data-grid__settings-item--5 input', true)
            .click('.sw-page__head-area')
            .expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--5`).to.have.text.that.contains('Email');
    },
    'reduce street column width using drag and drop': (browser) => {
        const page = customerPage(browser);

        browser.expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--1`).to.have.css('width').which.equals('250px');

        browser
            .moveToElement(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--2`, 10, 10)
            .waitForElementVisible(`${page.elements.dataGridColumn}--2 ${page.elements.dataGridColumn}-resize`)
            .dragAndDrop(
                `${page.elements.dataGridColumn}--2 ${page.elements.dataGridColumn}-resize`,
                `${page.elements.dataGridColumn}--2 ${page.elements.contextMenuButton}`,
                { xDrag: 0 }
            );

        browser.expect.element(`${page.elements.dataGridColumn}--2`).to.have.css('width').which.not.equals('250px');
    },
    'inline edit name': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0`, 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-edit')
            .fillField(`${page.elements.dataGridRow}--0 .sw-customer-list__inline-edit-fist-name input`, 'Meghan', true)
            .fillField(`${page.elements.dataGridRow}--0 .sw-customer-list__inline-edit-last-name input`, 'Markle', true)
            .click(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .waitForElementNotPresent('.is--inline-edit')
            .refresh()
            .expect.element(`.sw-data-grid__row--0 ${page.elements.columnName}`).to.have.text.that.equals('Meghan Markle');
    },
    'move city column before street column': (browser) => {
        const page = customerPage(browser);

        browser.expect.element('.sw-data-grid__cell--4').to.have.text.that.equals('City');
        browser
            .clickContextMenuItem('.sw-data-grid-settings__trigger', {
                scope: `${page.elements.dataGridHeader} .sw-data-grid__row`
            })
            .assert.containsText('.sw-data-grid__settings-item--4 label', 'City')
            .click('.sw-data-grid__settings-item--4 .icon--small-arrow-small-up')
            .assert.containsText('.sw-data-grid__settings-item--2 label', 'Street')
            .click('.sw-data-grid__settings-item--2 .icon--small-arrow-small-down ')
            .click('.sw-page__head-area');
        browser.expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--2`).to.have.text.that.equals('City');
        browser.expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--3`).to.have.text.that.equals('Street');
        browser.expect.element(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--4`).to.have.text.that.equals('Zip code');
    },
    'sort by name': (browser) => {
        const page = customerPage(browser);

        browser.expect.element(`.sw-data-grid__row--0 ${page.elements.columnName}`).to.have.text.that.equals('Meghan Markle');
        browser
            .click(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--1`)
            .waitForElementNotPresent('.sw-data-grid-skeleton');

        browser.click(`${page.elements.dataGridHeader} ${page.elements.dataGridColumn}--1`)
            .expect.element(`.sw-data-grid__row--0 ${page.elements.columnName}`).to.have.text.that.equals('Zapp Zarapp');
    },
    'navigate to customer': (browser) => {
        const page = customerPage(browser);

        browser.expect.element(`.sw-data-grid__row--0 ${page.elements.columnName} a`).to.have.text.that.equals('Zapp Zarapp');
        browser
            .click(`.sw-data-grid__row--0 ${page.elements.columnName} a`)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('Mr. Zapp Zarapp');
    }
};
