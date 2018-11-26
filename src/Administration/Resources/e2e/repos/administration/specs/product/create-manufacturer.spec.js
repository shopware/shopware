module.exports = {
    '@tags': ['manufacturer-create', 'manufacturer', 'create'],
    'navigate to manufacturer module and click on add manufacturer': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'enter manufacturer information': (browser) => {
        browser
            .assert.containsText('.smart-bar__header', 'New manufacturer')
            .fillField('input[name=name]', 'MAN-U-FACTURE')
            .fillField('input[name=link]', 'https://www.google.com/doodles')
            .setValue('.ql-editor', 'De-scribe THIS! \n \n Yours sincerely, \n \n The Manufacturer')
            .click('.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .click('.sw-alert button.sw-alert__close')
            .assert.containsText('.smart-bar__header','MAN-U-FACTURE')
            .click('.sw-media-upload-button__button-url')
            .waitForElementVisible('.sw-media-upload-url-modal')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/img/sw-media-background.png`)
            .click('.sw-modal__footer .sw-button--primary')
            .waitForElementVisible('.sw-alert--success')
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-alert--success')
            .click('.sw-button--primary')
            .waitForElementVisible('.sw-alert')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-alert .sw-alert__close');
    },
    'check if new manufacturer exists in overview': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/manufacturer/index')
            .assert.containsText('.smart-bar__header', 'Manufacturer')
            .waitForElementVisible('.sw-button__content')
            .assert.containsText('.sw-grid-row:first-child', 'MAN-U-FACTURE');
    },
    'open manufacturer details and change the given data': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-manufacturer-detail__empty-title)')
            .assert.containsText('.smart-bar__header','MAN-U-FACTURE')
            .fillField('input[name=name]', 'Minnie\'s Haberdashery')
            .fillField('input[name=link]', 'https://google.com/doodles')
            .setValue('.ql-editor', 'I would like to enter a meaningful description here. \n Ha, that was easy! \n Außerdem grüße ich \n Quentin Tarantino \n und \n meine Mama!!!!')
            .click('.sw-button--primary')
            .waitForElementPresent('.sw-alert__close')
            .click('.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__close')
            .click('.sw-button__content');
    },
    'open manufacturer overview again and delete manufacturer': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .click('.sw-modal__footer button.sw-button--primary');
    },
    'check if manufacturer has been deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .waitForElementVisible('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child')
            .assert.containsText('.sw-grid-row:first-child', 'shopware AG')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    after: (browser) => {
        browser.end();
    }
};
