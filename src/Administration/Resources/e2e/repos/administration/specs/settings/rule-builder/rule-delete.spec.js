const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-delete', 'delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('rule').then(() => {
            done();
        });
    },
    'navigate to rule index': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-rule')
            .assert.urlContains('#/sw/settings/rule/index');
    },
    'find rule to be deleted': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .fillGlobalSearchField(global.AdminFixtureService.basicFixture.name)
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.columnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'delete rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.columnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);


        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementVisible('.sw-modal')
            .expect.element('.sw-settings-rule-list__confirm-delete-text').to.have.text.that.contains(`Are you sure you want to delete the rule "${global.AdminFixtureService.basicFixture.name}"?`);


        browser.click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal')
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
