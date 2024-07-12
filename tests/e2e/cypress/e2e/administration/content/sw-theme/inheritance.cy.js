/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

const uuid = require('uuid/v4');

describe('Theme: Test Inheritance', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.createDefaultSalesChannel().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@content: check inherited theme', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/theme/*`,
            method: 'delete',
        }).as('deleteTheme');

        const themeId = uuid().replace(/-/g, '');
        const childThemeId = uuid().replace(/-/g, '');
        cy.createDefaultFixture('theme', {id: themeId, parentThemeId: null}).then(() => {
            cy.createDefaultFixture('theme', {id: childThemeId, parentThemeId: themeId}, 'theme-inheritance').then(() => {
                cy.createDefaultFixture('theme-child', {parentId: themeId, childId: childThemeId}).then(() => {
                    cy.visit(`${Cypress.env('admin')}#/sw/theme/manager/index`);
                    cy.get('.sw-skeleton').should('not.exist');
                    cy.get('.sw-loader').should('not.exist');
                });
            });
        });

        // show list of themes
        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Inherited Theme')
            .click();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        // search for Media card
        cy.contains('.sw-card__title', 'Media').scrollIntoView();

        // check inheritance text
        cy.contains('.sw-theme-manager-detail__inheritance-text', 'E2E Theme');

        cy.contains('.sw-inherit-wrapper__inheritance-label', 'Background').scrollIntoView();
        cy.get('.sw-inherit-wrapper.sw-field-id-sw-background-color .sw-colorpicker__input').should('have.value', '#aaa');

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary .sw-colorpicker__input').should('have.value', '#008490');

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary .sw-inheritance-switch--is-inherited').click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary input').type('{selectall}').type('#fff');

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary .sw-colorpicker__input').should('have.value', '#fff');

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary .sw-inheritance-switch--is-not-inherited').click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-primary .sw-colorpicker__input').should('have.value', '#008490');

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').should('have.value', '#474a57');

        cy.get('.smart-bar__back-btn').click();

        // go to default theme and change value
        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        // {selectall} selects all in the element (like strg+a) @see https://docs.cypress.io/api/commands/type#Arguments
        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').type('{selectall}').type('#a1a1a1');

        cy.get('.sw_theme_manager_detail__save-action').click();

        cy.contains('.sw-modal__footer .sw-button__content', 'Save').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.smart-bar__back-btn').click();

        // got to inherited theme and check inherited value
        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Inherited Theme')
            .click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').should('have.value', '#a1a1a1');

        cy.get('.smart-bar__back-btn').click();

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'E2E Theme')
            .click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-inheritance-switch--is-inherited').click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').type('{selectall}').type('#b2b2b2');

        cy.get('.sw-button-process__content').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.smart-bar__back-btn').click();

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Inherited Theme')
            .click();

        //remove media
        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.get('.sw-inherit-wrapper__inheritance-label')
            .contains('Desktop')
            .parent()
            .parent()
            .find('.sw-media-upload-v2__remove-icon')
            .click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').should('have.value', '#b2b2b2');

        // change of parent theme compiles child theme
        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .should('exist')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Storefront')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.smart-bar__back-btn').click();

        cy.get('.sw-theme-list-item')
            .get('.sw-theme-list-item__title')
            .contains('Inherited Theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();

        cy.get('.sw-inherit-wrapper__inheritance-label')
            .contains('Desktop')
            .parent()
            .parent()
            .find('.sw-inheritance-switch--is-not-inherited');

        cy.get('.smart-bar__back-btn').click();

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'E2E Theme')
            .click();

        cy.get('.sw-inherit-wrapper.sw-field-id-sw-color-brand-secondary .sw-colorpicker__input').type('{selectall}').type('#000');

        cy.get('.sw-button-process__content').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.navigation-offcanvas-headline')
            .should('have.css', 'color', 'rgb(0, 0, 0)');

    });
});
