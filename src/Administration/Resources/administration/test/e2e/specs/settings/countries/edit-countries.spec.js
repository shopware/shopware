module.exports = {
    '@tags': ['country-edit','country', 'edit'],
    'open country module': (browser) => {
        browser
            .assert.containsText('.sw-settings .collapsible-text', 'Settings')
            .click('.sw-admin-menu__navigation-link[href="#/sw/settings/index"]')
            .waitForElementVisible('.sw-settings-item[href="#/sw/settings/country/index"]')
            .click('.sw-settings-item[href="#/sw/settings/country/index"]');
    },
    'create new country': (browser) => {
        browser
            .click('a[href="#/sw/settings/country/create"]')
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/country/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .setValue('input[name=sw-field--country-name]', '1.Niemandsland')
            .waitForElementPresent('input[name=sw-field--country-active]')
            .click('input[name=sw-field--country-active]')
            .click('.sw-settings-country-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'Country "1.Niemandsland" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland');
    },
    'edit country': (browser) => {
        browser
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .waitForElementPresent('.sw-country-list__edit-action')
            .click('.sw-country-list__edit-action')
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.containsText('.sw-card__title', 'Settings')
            .clearValue('input[name=sw-field--country-name]')
            .setValue('input[name=sw-field--country-name]', '1.Niemandsland x2')
            .click('.sw-settings-country-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'Country "1.Niemandsland x2" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'verify edited country': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland x2');
    },
    'delete country': (browser) => {
        browser
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland x2')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the country "1.Niemandsland x2"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal');
    },
    after: (browser) => {
        browser.end();
    }
};
