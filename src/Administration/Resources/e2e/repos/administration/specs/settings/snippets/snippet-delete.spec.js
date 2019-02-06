const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'snippet-delete', 'snippets', 'delete'],
    '@disabled': !global.flags.isActive('next717'),
    before: (browser, done) => {
        global.SnippetFixtureService.setSnippetFixtures().then(() => {
            console.log('###',global.SnippetFixtureService.snippetFixture);
            done();
        });
    },
    'open snippet module and snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/snippet/index', 'Snippets')
            .waitForElementVisible('.sw-settings-snippet-set-list__actions')
            .getAttribute(`.sw-settings-snippet-set-list__actions ${page.elements.primaryButton}`, 'disabled', function (result) {
                this.assert.equal(result.value, 'true');
            })
            .tickCheckbox(`${page.elements.gridRow}--1 input[type=checkbox]`, true)
            .getAttribute(`.sw-settings-snippet-set-list__actions ${page.elements.primaryButton}`, 'disabled', function (result) {
                this.assert.equal(result.value, null);
            })
            .click(`.sw-settings-snippet-set-list__actions ${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element('.sw-settings_snippet_list__smart-bar-title-text').to.have.text.that.equals('Snippets of "BASE en_GB"');
    },
    'verify snippet set to be reset': (browser) => {
        const page = settingsPage(browser);

        browser
            .fillGlobalSearchField(global.SnippetFixtureService.snippetFixture.translationKey)
            .expect.element(`${page.elements.gridRow}--0 .sw-grid-column--left`).to.have.text.that.equals(global.SnippetFixtureService.snippetFixture.translationKey);
    },
    'delete snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} ${page.elements.modal}__body`, `Are you sure you want to reset the snippet "${global.SnippetFixtureService.snippetFixture.translationKey}"?`)
            .tickCheckbox('input[name=sw-field--allSelectedChecked]', true)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.loader);
    },
    'verify deletion': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element(`${page.elements.gridRow}--0 a`).to.have.text.that.not.equals('A Set Name Snippet');
    },
    after: (browser) => {
        browser.end();
    }
};
