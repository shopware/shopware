const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-edit', 'snippets', 'snippets', 'edit'],
    before: (browser, done) => {
        global.SnippetFixtureService.setSnippetFixtures().then(() => {
            done();
        });
    },
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-snippet')
            .assert.urlContains('#/sw/settings/snippet/index');
    },
    'open snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.not.be.enabled;
        browser
            .tickCheckbox(`${page.elements.gridRow}--1 .sw-field__checkbox input`, true)
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.be.enabled;

        browser
            .click('.sw-settings-snippet-set-list__edit-set-action')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Snippets of "BASE en_GB"');
    },
    'verify snippet to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--id`).to.have.text.that.equals(global.SnippetFixtureService.snippetFixture.translationKey);
    },
    'edit snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-settings-snippet-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.SnippetFixtureService.snippetFixture.translationKey);

        browser
            .fillField(
                '.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]',
                'Mine yours theirs', true
            )
            .expect.element('.sw-snippet-detail__save-action').to.be.enabled;

        browser
            .click('.sw-snippet-detail__save-action')
            .waitForElementNotPresent(page.elements.loader)
            .checkNotification(`Snippet for "${global.SnippetFixtureService.snippetFixture.translationKey}" has been saved successfully.`);
    },
    'verify changed snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains('Mine yours theirs');
    }
};
