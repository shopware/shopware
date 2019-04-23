const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-reset', 'snippets', 'edit', 'reset'],
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
    'inline edit first snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0`, 1, 1)
            .doubleClick()
            .waitForElementPresent('.is--inline-edit ')
            .fillField(
                `${page.elements.dataGridRow}--0 .sw-field__input input`,
                '- some more'
            )
            .waitForElementVisible(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .click(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .waitForElementNotPresent('.is--inline-edit')
            .waitForElementNotPresent(page.elements.loader)
            .checkNotification('has been saved successfully.')
            .expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains('- some more');
    },
    'reset first snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.contains('Are you sure you want to reset the snippet');

        browser
            .click(`${page.elements.modalFooter} button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .checkNotification('has been reset to');
    },
    'verify deletion of snippet': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.not.contains(global.SnippetFixtureService.snippetFixture.translationKey);
    }
};
