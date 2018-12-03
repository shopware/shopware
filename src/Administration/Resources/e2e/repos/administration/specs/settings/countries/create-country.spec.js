module.exports = {
    '@tags': ['setting','country-create', 'country', 'create'],
    'open country module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries');
    },
    'create new country': (browser) => {
        browser
            .click('a[href="#/sw/settings/country/create"]')
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/country/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland')
            .waitForElementPresent('input[name=sw-field--country-active]')
            .tickCheckbox('input[name=sw-field--country-active]', 'on')
            .click('.sw-settings-country-detail__save-action')
            // .waitForElementVisible('.sw-notifications .sw-alert')
            // .assert.containsText('.sw-alert__message', 'Country "1.Niemandsland" has been saved successfully.')
            .checkNotification( 'Country "1.Niemandsland" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland');
    },
    'delete country': (browser) => {
        browser
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the country "1.Niemandsland"?')
            .click('.sw-modal__footer button.sw-button--primary')
            // .waitForElementVisible('.sw-notifications .sw-alert')
            // .assert.containsText('.sw-notifications .sw-alert', 'Country "1.Niemandsland" has been deleted successfully.');
            .checkNotification('Country "1.Niemandsland" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
