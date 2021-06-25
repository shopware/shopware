/// <reference types="Cypress" />

import ManufacturerPageObject from '../../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturer: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-manufacturer');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/manufacturer/index`);
            });
    });

    it('@catalogue: create and read manufacturer', () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer`,
            method: 'post'
        }).as('saveData');

        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Manufacturer');
        cy.get(page.elements.primaryButton).contains('Add manufacturer').click();
        cy.url().should('contain', '#/sw/manufacturer/create');

        cy.get('input[name=name]').clearTypeAndCheck('MAN-U-FACTURE');
        cy.get('input[name=link]').clearTypeAndCheck('https://google.com/doodles');
        cy.get(page.elements.manufacturerSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();
    });

    it('@catalogue: edit and read manufacturer', () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'patch'
        }).as('saveData');

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=name]').clearTypeAndCheck('be.visible');
        cy.get('input[name=name]').clear().type('What does it means?(TM)');
        cy.get('input[name=link]').clear().type('https://google.com/doodles');

        cy.get(page.elements.manufacturerSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.successIcon).should('be.visible');
    });

    it('@catalogue: edit and read manufacturer with input purification [FEATURE_NEXT_15172]', () => {
        cy.onlyOnFeature('FEATURE_NEXT_15172');

        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/_admin/sanitize-html`,
            method: 'post'
        }).as('sanitizePreview');

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=name]').clearTypeAndCheck('be.visible');
        cy.get('input[name=name]').clear().type('What does it means?(TM)');
        cy.get('input[name=link]').clear().type('https://google.com/doodles');

        // write js code via code editor into manufacturer description
        cy.get('.sw-text-editor__content-editor').clear().type('Manufacturer description');
        cy.get('.sw-text-editor-toolbar-button__type-codeSwitch').click();
        cy.get('.sw-code-editor').type('<script>alert("Danger!");'); // closing `</script>` inserted by ace editor
        cy.get('input[name=name]').click(); // trigger blur event on sw-code-editor component

        cy.wait('@sanitizePreview').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-code-editor__sanitized-hint').should('be.visible');

        cy.get('.sw-text-editor-toolbar-button__type-codeSwitch').click();
        cy.get('.sw-text-editor__content').contains('Manufacturer description');

        cy.get(page.elements.manufacturerSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-text-editor__content').contains('Manufacturer description');
        cy.get(page.elements.successIcon).should('be.visible');
    });

    it('@catalogue: delete manufacturer', () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'delete'
        }).as('saveData');

        // Delete manufacturer
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} ${page.elements.modal}__body p`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify updated manufacturer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.contains('MAN-U-FACTURE').should('not.exist');
    });
});
