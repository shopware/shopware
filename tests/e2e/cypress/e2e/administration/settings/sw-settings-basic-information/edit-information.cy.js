// / <reference types="Cypress" />delete country

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';

describe('Basic Informaion: Edit assignments', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/basic/information/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: assign 404 error layout and test rollout', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.createDefaultFixture('cms-page', {}, 'cms-error-page');

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
        }).as('saveData');

        // Assign 404 layout to all sales channels
        cy.get('.sw-system-config__card--1').scrollIntoView();
        cy.get('.sw-system-config__card--1').should('be.visible');
        cy.contains('.sw-system-config__card--1 .sw-card__title', 'Shop pages');
        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]').scrollIntoView();
        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]').should('be.visible');

        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]')
            .typeSingleSelectAndCheck(
                '404 Layout',
                '.sw-cms-page-select[name="core.basicInformation.http404Page"] .sw-entity-single-select',
            );

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.contains(
            '.sw-cms-page-select[name="core.basicInformation.http404Page"] ' +
                    '.sw-entity-single-select__selection-text',
            '404 Layout');
        cy.visit('/non-existent/', { failOnStatusCode: false });

        cy.contains('.cms-page .cms-element-text', '404 - Not Found');
    });

    it('@settings: assign maintenance layout and test rollout', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        const salesChannelPage = new SalesChannelPageObject();

        cy.createDefaultFixture('cms-page', {}, 'cms-maintenance-page');

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
        }).as('saveSettings');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'PATCH',
        }).as('saveSalesChannel');

        // Assign Maintenance layout to all sales channels
        cy.get('.sw-system-config__card--1').scrollIntoView();
        cy.get('.sw-system-config__card--1').should('be.visible');
        cy.contains('.sw-system-config__card--1 .sw-card__title', 'Shop pages');
        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]').scrollIntoView();
        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]').should('be.visible');

        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]')
            .typeSingleSelectAndCheck(
                'Maintenance',
                '.sw-cms-page-select[name="core.basicInformation.maintenancePage"] .sw-entity-single-select',
            );

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSettings').its('response.statusCode').should('equal', 204);

        cy.contains(
            '.sw-cms-page-select[name="core.basicInformation.maintenancePage"] ' +
            '.sw-entity-single-select__selection-text',
            'Maintenance Layout');

        salesChannelPage.openSalesChannel('Storefront', 1);

        cy.get('input[name="sw-field--salesChannel-maintenance"]').click().should('have.value', 'on');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSalesChannel').its('response.statusCode').should('equal', 204);

        cy.visit('/', { failOnStatusCode: false });

        cy.contains('.cms-page .cms-element-text', 'Maintenance');
    });

    it('@settings: test default maintenance layout rollout', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        const salesChannelPage = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'PATCH',
        }).as('saveSalesChannel');

        salesChannelPage.openSalesChannel('Storefront', 1);

        cy.get('input[name="sw-field--salesChannel-maintenance"]').click().should('have.value', 'on');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSalesChannel').its('response.statusCode').should('equal', 204);

        cy.visit('/', { failOnStatusCode: false });

        cy.contains('.content-main h1', 'Maintenance mode');
    });

    it('@settings: change active captcha and test input field show when google recaptcha selected', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-system-config__card--3').scrollIntoView();
        cy.get('.sw-system-config__card--3').should('be.visible');
        cy.contains('.sw-system-config__card--3 .sw-card__title', 'CAPTCHA');
        cy.get('.sw-settings-captcha-select-v2').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2').should('be.visible');

        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').clear();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').clear();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').should('be.empty');

        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').type('3');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-result.sw-select-option--0').contains('Google reCAPTCHA v3').click();

        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3 input[name="googleReCaptchaV3ThresholdScore"]').clear().type('0.5');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-settings-captcha-select-v2').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2 .sw-settings-captcha-select-v2__google-recaptcha-v3')
            .should('be.visible');
        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3-description').should('be.visible');
        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3 input[name="googleReCaptchaV3ThresholdScore"]')
            .should('have.value', '0.5');
    });
});
