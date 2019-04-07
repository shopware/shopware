const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'snippet-set-create', 'snippets', 'snippet-set', 'create'],
    'open snippet module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-snippet')
            .assert.urlContains('#/sw/settings/snippet/index');
    },
    'create a new snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .click('.sw-settings-snippet-set-list__action-add')
            .waitForElementVisible(`${page.elements.gridRow}--0.is--inline-editing`)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Snip Snap')
            .fillSelectField(`${page.elements.gridRow}--0 select[name=sw-field--item-baseFile]`, 'messages.en_GB')
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .checkNotification('Snippet set "Snip Snap" has been saved successfully.');
    },
    'go back to listing and verify snippet set': (browser) => {
        const page = settingsPage(browser);

        browser
            .refresh()
            .expect.element(`${page.elements.gridRow}--2 a`).to.have.text.that.equals('Snip Snap');
    }
};
