// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';
import variantProduct from '../../../fixtures/variant-product';

describe('Rule builder: Test display variant information at condition', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                return cy.createProductFixture(variantProduct);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@rule: Display variant information at rule condition input', () => {
        cy.window().then(() => {
            const page = new RulePageObject();

            cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

            cy.get(page.elements.loader).should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
            cy.clickContextMenuItem(
                '.sw-entity-listing__context-menu-edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
                .first()
                .as('first-and-container');
            cy.get('@first-and-container').should('be.visible');

            cy.get('@first-and-container').within(() => {
                cy.get('.sw-condition').as('condition-general');

                cy.onlyOnFeature('FEATURE_NEXT_17016', () => {
                    page.selectTypeAndOperator('@condition-general', 'Line item', 'Is one of');
                });
                cy.skipOnFeature('FEATURE_NEXT_17016', () => {
                    page.selectTypeAndOperator('@condition-general', 'Line items in cart', 'Is one of');
                });

                cy.get('@condition-general').within(() => {
                    cy.get('.sw-select input').last().clearTypeAndCheck('Variant product');

                    const selectResultList = cy.window().then(() => {
                        return cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper'));
                    });

                    selectResultList.should('be.visible');
                    selectResultList.find('.sw-select-result').should('have.length', 4);
                    selectResultList.find('.sw-product-variant-info__specification').as('variant-info');
                    cy.get('@variant-info').should('contain', 'red');
                    cy.get('@variant-info').should('contain', 'green');
                    cy.get('@variant-info').should('contain', 'blue');
                });
            });
        });
    });

    it('@rule: Display variant information at rule condition list', () => {
        cy.window().then(() => {
            cy.intercept({
                url: `${Cypress.env('apiPath')}/_action/sync`,
                method: 'POST'
            }).as('saveData');

            const page = new RulePageObject();

            cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

            cy.get(page.elements.loader).should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
            cy.clickContextMenuItem(
                '.sw-entity-listing__context-menu-edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
                .first()
                .as('first-and-container');
            cy.get('@first-and-container').should('be.visible');

            cy.get('@first-and-container').within(() => {
                cy.get('.sw-condition').as('condition-general');

                cy.onlyOnFeature('FEATURE_NEXT_17016', () => {
                    page.selectTypeAndOperator('@condition-general', 'Line item', 'Is one of');
                });
                cy.skipOnFeature('FEATURE_NEXT_17016', () => {
                    page.selectTypeAndOperator('@condition-general', 'Line items in cart', 'Is one of');
                });

                cy.get('@condition-general').within(() => {
                    cy.get('.sw-select input').last().clearTypeAndCheck('Variant product');

                    const selectResultList = cy.window().then(() => {
                        return cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper'));
                    });

                    selectResultList.find('.sw-product-variant-info__specification').contains('red').click();
                });
            });

            cy.get('button.sw-button').contains('Save').click();
            cy.wait('@saveData')
                .its('response.statusCode').should('equal', 200);
            cy.get('.sw-product-variant-info__specification').should('have.length', 1).contains('red');
        });
    });
});
