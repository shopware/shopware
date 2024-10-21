// / <reference types="Cypress" />
import RuleBuilderPageObject from '../../support/pages/module/sw-rule.page-object';

describe('Creating custom fields and assigning to various models', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/create`);
                cy.contains('.sw-empty-state__title', 'Nog geen vrije tekstvelden.');
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@package: create custom text field and verify from categories, product', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('category');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/custom-field-set`,
            method: 'POST',
        }).as('saveData');

        const typeOfTheCustom = 'Tekstveld';

        cy.url().should('include', 'custom/field/create');

        // create new custom field
        cy.get('.sw-settings-set-detail__save-action').should('be.enabled');
        cy.get('#sw-field--set-name').clearTypeAndCheck(`cf set_${typeOfTheCustom}`);
        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck(`cf set_${typeOfTheCustom}`);
        cy.get('.sw-select').typeMultiSelectAndCheck('Producten');
        cy.get('.sw-select').typeMultiSelectAndCheck('Categorieën');
        cy.get('.sw-empty-state').should('exist');

        // saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-button.sw-button--small.sw-custom-field-list__add-button').click();
        cy.get('#sw-field--currentCustomField-config-customFieldType').select(typeOfTheCustom);
        cy.get('.sw-custom-field-type-base .sw-field--default:nth-of-type(1) [type]').type(typeOfTheCustom);
        cy.get('.sw-button.sw-custom-field-detail__footer-save').click();
        cy.contains('.sw-custom-field-list__custom-field-label', typeOfTheCustom).should('be.visible');
        cy.get('.sw-settings-set-detail__save-action').click();

        // check custom text field from the categories
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');
        cy.get('.sw-tree-item__label').click();
        cy.contains('Vrije tekstvelden').scrollIntoView();
        cy.get('[for="cf set_tekstveld_"]').should('be.visible')
            .and('include.text', typeOfTheCustom);

        cy.get('.sw-button-process').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.wait('@category').its('response.statusCode').should('equal', 200);

        // check the existence of the custom field from the product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.get('.sw-data-grid__table a').click();
        cy.url().should('include', 'product/detail');
        cy.get('[title="specificaties"]').should('be.visible').click();
        cy.url().should('include', 'specifications');
        cy.contains('Maatregelen & verpakking');
        cy.get('.sw-tabs__custom-content').find('.sw-field').find('.sw-field__label > label').scrollIntoView();
        cy.get('[for="cf set_tekstveld_"]')
            .should('be.visible')
            .and('include.text', typeOfTheCustom);
    });

    it('@package: create custom number field and verify with rule builder', { tags: ['quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('category');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/category/**`,
            method: 'PATCH',
        }).as('updateCategory');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/custom-field-set`,
            method: 'POST',
        }).as('saveData');

        const page = new RuleBuilderPageObject();
        const typeOfTheCustom = 'Nummer veld';

        cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/create`);
        cy.contains('.sw-card__title', 'Algemene informatie');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'custom/field/create');

        // create new custom field
        cy.get('.sw-settings-set-detail__save-action').should('be.enabled');
        cy.get('#sw-field--set-name').clearTypeAndCheck(`cf set_${typeOfTheCustom}`);
        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck(`cf set_${typeOfTheCustom}`);
        cy.get('.sw-select').click();

        cy.contains('.sw-select-result', 'Producten').click({ force: true });
        cy.contains('.sw-select-result', 'Categorieën').click({ force: true });
        cy.contains('.sw-select-result', 'Klanten').click({ force: true });

        cy.contains('.sw-label', 'Producten');
        cy.contains('.sw-label', 'Categorieën');
        cy.contains('.sw-label', 'Klanten');
        cy.get('.sw-empty-state').should('exist');

        // saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Nieuwe vrij tekstveld').click();
        cy.get('#sw-field--currentCustomField-config-customFieldType').select(typeOfTheCustom);
        cy.get('.sw-custom-field-type-base .sw-field--default:nth-of-type(1) [type]').type(typeOfTheCustom);
        cy.get('.sw-button.sw-custom-field-detail__footer-save').click();
        cy.contains('.sw-custom-field-list__custom-field-label', typeOfTheCustom).should('be.visible');
        cy.get('.sw-settings-set-detail__save-action').click();

        // check custom fields from the categories
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.contains('.sw-tree-item__label', 'Home');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');

        cy.get('.sw-tree-item__label').click();
        cy.contains('.sw-category-detail-base .sw-card__title', 'Algemeen');
        cy.contains('.sw-text-editor__label', 'Beschrijving');
        cy.contains('.sw-media-upload-v2__label', 'Schermafbeelding');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('Vrije tekstvelden').scrollIntoView();

        cy.get('[for="cf set_nummer veld_"]').should('exist');
        cy.get('[for="cf set_nummer veld_"]').scrollIntoView();
        cy.get('[for="cf set_nummer veld_"]').should('be.visible');
        cy.get('.sw-tab--name-cf.set_Nummer').click();
        cy.get('[for="cf set_nummer veld_"]').should('be.visible');

        cy.get('.sw-button-process').click();
        cy.wait('@category').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // create rule builder and define custom field
        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/create/base`);
        cy.contains('.sw-card__title', 'Algemeen');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'rule/create/base');
        cy.get('input#sw-field--rule-name').type('custom rule builder');
        cy.get('input#sw-field--rule-priority').type('1');

        cy.get(page.elements.searchCondition).type('Klant met aangepast veld');
        cy.contains('Klant met aangepast veld').click({ force: true });
        cy.get('.is--placeholder.sw-entity-single-select__selection-text').click();
        cy.contains(`cf set_${typeOfTheCustom}`);
    });
});
