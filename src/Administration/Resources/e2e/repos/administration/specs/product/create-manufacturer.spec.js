module.exports = {
    '@tags': ['manufacturer-create', 'manufacturer', 'create'],
    'navigate to manufacturer module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product')
            .openMainMenuEntry('#/sw/manufacturer/index', 'Manufacturer')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'enter manufacturer information': (browser) => {
    browser
            .assert.containsText('.smart-bar__header', 'New manufacturer')
            .fillField('input[name=name]', 'MAN-U-FACTURE')
            .fillField('input[name=link]', 'https://tomate.su/ist-seehofer-noch-im-amt/')
            .setValue('.ql-editor.ql-blank', 'De-scribe THIS! \n \n Yours sincerely, \n \n The Manufacturer')
            .click('.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .click('.sw-alert button.sw-alert__close')
            .assert.containsText('.smart-bar__header','MAN-U-FACTURE')
            .click('.sw-media-upload-button__button-url')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/img/sw-media-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert__close')
            .click('.sw-button--primary')
            .waitForElementPresent('.sw-alert__close')
            .click('.sw-alert button.sw-alert__close')
            .click('.sw-button');
    },
    'check if new manufacturer exists in overview': (browser) => {
        browser
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText('.smart-bar__header', 'Manufacturer')
            .waitForElementPresent('sw-sidebar__navigation')
            .click('sw-sidebar-navigation-item')
            .waitForElementPresent('.sw-grid-row:first-child', 'MAN-U-FACTURE');
    },
    'edit manufacturer information': (browser) => {
        browser
            .waitForElementPresent('.sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementPresent('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementPresent('.smart-bar__header', 'MAN-U-FACTURE')
            .assert.containsText('.smart-bar__header','MAN-U-FACTURE')
            .fillField('input[name=name]', 'Ramschladen')
            .fillField('input[name=link]', 'https://google.com/doodles')
            .setValue('.ql-editor', 'Bitch, get outta my way, bitch. ')
            .click('.sw-button--primary')
            .click('.sw-alert__close')
            .click('.sw-button')
            .waitForElementPresent('.smart-bar__header', 'Manufacturer')
            .pause(2000)
            .waitForElementVisible('sw-sidebar__navigation')
            .click('sw-sidebar-navigation-item')
            .pause(2000)
            .assert.containsText('.sw-grid-row:first-child', 'Ramschladen')
            .pause(5000);
    },

    after: (browser) => {
        browser.end();
    }
};
