// / <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product groups: Visual tests', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createDefaultFixture('product-stream').then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic product stream workflow', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('searchProducts');

        const page = new ProductStreamObject();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__row--0').should('be.visible');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-data-grid__cell--updatedAt .sw-data-grid__cell-content', '01 Jan 2018, 00:00');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Product groups] Listing', '.sw-product-stream-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains(page.elements.smartBarHeader, 'Dynamic product groups');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is equal to any of',
                value: ['Product name'],
            },
        );

        cy.contains('button.sw-button', 'Preview').click();

        // Take snapshot for visual testing
        cy.get('.sw-product-stream-modal-preview').should('be.visible');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@searchProducts')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-data-grid .sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-modal').should('be.visible');

        cy.contains('.sw-modal__header', 'Preview (1)');

        cy.handleModalSnapshot('Preview');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Product groups] Detail, preview', '.sw-product-stream-modal-preview .sw-data-grid__row--0', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name', 'Product name');
            cy.get('.sw-modal__close').click();
        });

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is not equal to any of',
                value: [],
            },
        );

        // Take snapshot for visual testing
        cy.get('.sw-select-selection-list__input').type('{esc}');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Product groups] Detail, with conditions', '.sw-product-stream-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-condition-or-container__actions > :nth-child(1) > .sw-button')
            .click();

        cy.get(':nth-child(3) > .sw-product-stream-filter > .sw-product-stream-filter__container > .sw-product-stream-filter__selects > .sw-product-stream-field-select > .sw-field > .sw-block-field__block > .sw-select__selection > .sw-single-select__selection')
            .click();

        cy.get('.sw-select-result-list__content')
            .should('be.visible');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Product groups] Detail, with popover menu', '.sw-product-stream-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
