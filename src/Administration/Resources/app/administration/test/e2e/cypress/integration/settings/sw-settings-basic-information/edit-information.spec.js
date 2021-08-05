// / <reference types="Cypress" />delete country

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Basic Informaion: Edit assignments', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/basic/information/index`);
            });
    });

    it('@settings: assign 404 error layout and test rollout', () => {
        cy.createDefaultFixture('cms-page', {}, 'cms-error-page');

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'post'
        }).as('saveData');

        // Assign 404 layout to all sales channels
        cy.get('.sw-card.sw-system-config__card--1').scrollIntoView();
        cy.get('.sw-card.sw-system-config__card--1').should('be.visible');
        cy.get('.sw-card.sw-system-config__card--1 .sw-card__title').contains('Shop pages');
        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]').scrollIntoView();
        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]').should('be.visible');

        cy.get('.sw-cms-page-select[name="core.basicInformation.http404Page"]')
            .typeSingleSelectAndCheck(
                '404 Layout',
                '.sw-cms-page-select[name="core.basicInformation.http404Page"] .sw-entity-single-select'
            );

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get(
                '.sw-cms-page-select[name="core.basicInformation.http404Page"] ' +
                        '.sw-entity-single-select__selection-text'
            ).contains('404 Layout');
        });

        // Request storefront
        cy.request({ url: '/non-existent/', failOnStatusCode: false }).then(request => {
            expect(request).to.have.property('status', 404);
        });

        cy.visit('/non-existent/', { failOnStatusCode: false });

        cy.get('.cms-page .cms-element-text').contains('404 - Not Found');
    });

    it('@settings: assign maintenance layout and test rollout', () => {
        const salesChannelPage = new SalesChannelPageObject();

        cy.createDefaultFixture('cms-page', {}, 'cms-maintenance-page');

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'post'
        }).as('saveSettings');

        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'patch'
        }).as('saveSalesChannel');

        // Assign Maintenance layout to all sales channels
        cy.get('.sw-card.sw-system-config__card--1').scrollIntoView();
        cy.get('.sw-card.sw-system-config__card--1').should('be.visible');
        cy.get('.sw-card.sw-system-config__card--1 .sw-card__title').contains('Shop pages');
        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]').scrollIntoView();
        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]').should('be.visible');

        cy.get('.sw-cms-page-select[name="core.basicInformation.maintenancePage"]')
            .typeSingleSelectAndCheck(
                'Maintenance',
                '.sw-cms-page-select[name="core.basicInformation.maintenancePage"] .sw-entity-single-select'
            );

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSettings').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get(
                '.sw-cms-page-select[name="core.basicInformation.maintenancePage"] ' +
                        '.sw-entity-single-select__selection-text'
            ).contains('Maintenance Layout');
        });

        salesChannelPage.openSalesChannel('Storefront', 1);

        cy.get('input[name="sw-field--salesChannel-maintenance"]').click().should('have.value', 'on');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSalesChannel').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit('/', { failOnStatusCode: false });

        cy.get('.cms-page .cms-element-text').contains('Maintenance');
    });

    it('@settings: test default maintenance layout rollout', () => {
        const salesChannelPage = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'patch'
        }).as('saveSalesChannel');

        salesChannelPage.openSalesChannel('Storefront', 1);

        cy.get('input[name="sw-field--salesChannel-maintenance"]').click().should('have.value', 'on');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveSalesChannel').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit('/', { failOnStatusCode: false });

        cy.get('.content-main h1').contains('Maintenance mode');
    });

    // NEXT-16105 - Flaky, looks like the test does not wait for the clear of the multi select
    it.skip('@settings: change active captcha and test input field show when google recaptcha selected', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'post'
        }).as('saveData');

        cy.get('.sw-card.sw-system-config__card--3').should('be.visible');
        cy.get('.sw-card.sw-system-config__card--3 .sw-card__title').contains('CAPTCHA');
        cy.get('.sw-settings-captcha-select-v2').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2').should('be.visible');

        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').clear();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').clear();
        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select input').should('be.empty');

        cy.get('.sw-settings-captcha-select-v2 .sw-multi-select')
            .typeMultiSelectAndCheck('Google reCAPTCHA v3');

        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3 input[name="googleReCaptchaV3ThresholdScore"]').clear().type('0.5');

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-settings-captcha-select-v2').scrollIntoView();
        cy.get('.sw-settings-captcha-select-v2 .sw-settings-captcha-select-v2__google-recaptcha-v3')
            .should('be.visible');
        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3-description').should('be.visible');
        cy.get('.sw-settings-captcha-select-v2__google-recaptcha-v3 input[name="googleReCaptchaV3ThresholdScore"]')
            .should('have.value', '0.5');
    });
});
