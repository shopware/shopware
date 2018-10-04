module.exports = {
    '@tags': ['country-create', 'country', 'create'],
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
            .click('.sw_settings_country_detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'The country 1.Niemandsland has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-country-name:first-child')
            .assert.containsText('.sw-country-list__column-country-name:first-child', '1.Niemandsland');
    },
    'delete country': (browser) => {
        browser
            .assert.containsText('.sw-country-list__column-country-name:first-child', '1.Niemandsland')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure, you want to delete the country 1.Niemandsland?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-notifications .sw-alert', 'The country 1.Niemandsland has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
