// / <reference types="Cypress" />

const DEFAULT_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const LOCALE = 'en-GB';
const FALLBACK_LOCALE = 'en-GB';

describe('SDK Tests: Context', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            })
            .then(() => {
                cy.onlyOnFeature('FEATURE_NEXT_17950');

                cy.get('.sw-dashboard-statistics__card-headline')
                    .should('be.visible');

                cy.get('.sw-loader').should('not.exist');
                cy.get('.sw-skeleton').should('not.exist');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            })
    });

    it('@sdk: get current language', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Get the current language')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Get current language')
            .click();

        cy.log('Check the current language')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains(`system-language-ID: ${DEFAULT_LANGUAGE_ID}`);

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains(`languageId: ${DEFAULT_LANGUAGE_ID}`);
    })

    it('@sdk: subscribe on language changes', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Change the language of the current user')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.get('.navigation-list-item__sw-order-index')
            .click();

        cy.contains('.smart-bar__header', 'Orders');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.sw-language-switch input')
            .click()

        cy.contains('.sw-select-result', 'Deutsch')
            .click();

        cy.log('Check if subscription of language works')

        cy.get('.sw-alert__title')
            .should('be.visible');

        cy.get('.sw-alert__title')
            .contains('Language changes')

        cy.get('.sw-alert__message')
            .contains('languageId: '); // ID changes everytime therefore no check
        cy.get('.sw-alert__message')
            .contains(`systemLanguageId: ${DEFAULT_LANGUAGE_ID}`);
    })

    it('@sdk: get current environment', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Get the current environment')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Get current environment')
            .click();

        cy.log('Check the current environment')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains(`Environment: production`);
    })

    it('@sdk: get current locale', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Get the current locale')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Get current locale')
            .click();

        cy.log('Check the current locale')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains(`Locale: ${LOCALE}`);

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains(`Fallback Locale: ${FALLBACK_LOCALE}`);
    })

    it('@sdk: subscribe on locale changes', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Change the locale of the current user')

        cy.get('.sw-admin-menu__user-name')
            .click();

        cy.contains('Your profile')
            .click();

        cy.contains('select', 'English (United Kingdom)')
            .select('German (Germany)');

        cy.contains('button', 'Save')
            .click();

        cy.get('#sw-field--confirm-password')
            .type('shopware')

        cy.contains('button', 'Confirm')
            .click();

        cy.log('Check if subscription of locale works')

        cy.get('.sw-alert__title')
            .should('be.visible');

        cy.get('.sw-alert__title')
            .contains('Locale changes')

        cy.get('.sw-alert__message')
            .contains('locale: de-DE');
        cy.get('.sw-alert__message')
            .contains('fallbackLocale: en-GB');
    })

    it('@sdk: get current shopware version', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Get the current Shopware version')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Get current Shopware version')
            .click();

        cy.log('Check the current Shopware version')

        // Only check if the version starts with 6. to avoid adjustments for each version
        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains('Shopware version: 6.');
    })

    it('@sdk: get app information', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Get the app information')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Get app information')
            .click();

        cy.log('Check the app information')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains('App name: TestPlugin');

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains('App type: plugin');
    })
})
