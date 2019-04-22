const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-custom', 'snippets', 'create', 'delete'],

    'open snippet module': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-snippet')
            .waitForElementNotPresent(page.elements.loader);
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
    'create snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .click(page.elements.primaryButton)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('New snippet');
        browser.expect.element('.sw-snippet-detail__save-action').to.not.be.enabled;

        browser.fillField('input[name=sw-field--translationKey]', 'a.woodech');
        browser.fillField('.sw-settings-snippet-detail__translation-field--0 input[name=sw-field--snippet-value]', 'Ech');
        browser.fillField('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]', 'Blach');

        browser.expect.element('.sw-snippet-detail__save-action').to.be.enabled;
        browser.click('.sw-snippet-detail__save-action')
            .checkNotification('Snippet for "a.woodech" has been saved successfully.');
    },
    'open all snippet sets': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementNotPresent(page.elements.loader)
            .click(page.elements.smartBarBack)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.not.be.enabled;
        browser
            .tickCheckbox('input[name=sw-field--allSelectedChecked]', true)
            .expect.element('.sw-settings-snippet-set-list__edit-set-action').to.be.enabled;

        browser
            .click('.sw-settings-snippet-set-list__edit-set-action')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Snippets');
    },
    'filter for and verify snippet to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('.icon--default-action-filter')
            .tickCheckbox('input[name=addedSnippets]', true)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--id`).to.have.text.that.equals('a.woodech');
    },
    'delete snippet': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.contains('Are you sure you want to delete the snippets');

        browser
            .click(`${page.elements.modalFooter} button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal);
    },
    'verify deletion of snippet': (browser) => {
        const page = settingsPage(browser);

        browser.waitForElementNotPresent(`${page.elements.dataGridRow}--0`);
    }
};
