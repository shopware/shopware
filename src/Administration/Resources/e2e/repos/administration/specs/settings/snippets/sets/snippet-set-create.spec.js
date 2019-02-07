const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings','snippet-set-create', 'snippets', 'snippet-set', 'create'],
    '@disabled': !global.flags.isActive('next717'),
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/snippet/index', 'Snippets');
    },
    'create a new snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible('.sw-settings-snippet-set-list__action-add')
            .click('.sw-settings-snippet-set-list__action-add')
            .waitForElementVisible(`${page.elements.gridRow}--0.is--inline-editing`)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Snip Snap')
            .fillSelectField(`${page.elements.gridRow}--0 select[name=sw-field--item-baseFile]`, 'messages.en_GB')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .checkNotification('Snippet set "Snip Snap" has been saved successfully.');
    },
    'go back to listing and verify tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .refresh()
            .expect.element(`${page.elements.gridRow}--2 a`).to.have.text.that.equals('Snip Snap');
    },
    after: (browser) => {
        browser.end();
    }
};
