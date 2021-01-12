// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Test order state', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            });
    });

    it('@base @order: add document to order', () => {
        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/**/document/invoice`,
            method: 'post'
        }).as('createDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/document`,
            method: 'post'
        }).as('findDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('findOrder');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        // Find documents
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-document-grid-button',
            null,
            'Invoice'
        );

        // Generate invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
            .click();

        // Verify invoice
        cy.wait('@createDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.responseBody).to.have.property('documentId');
            expect(xhr.responseBody).to.have.property('documentDeepLink');

            const documentId = xhr.response.body.documentId;
            const documentDeepLink = xhr.response.body.documentDeepLink;

            cy.getCookie('bearerAuth').then((bearerAuth) => {
                return JSON.parse(bearerAuth.value).access;
            }).then((authToken) => {
                return cy.request(
                    {
                        headers: {
                            Accept: 'application/vnd.api+json',
                            Authorization: `Bearer ${authToken}`,
                            'Content-Type': 'application/json'
                        },
                        method: 'GET',
                        url: `/api/_action/document/${documentId}/${documentDeepLink}`
                    }
                );
            }).then((xhrDeepLink) => {
                expect(xhrDeepLink).to.have.property('status', 200);
                expect(xhrDeepLink.headers).to.have.property('content-type', 'application/pdf');
            });
        });


        cy.wait('@findDocumentCall').then((xhr) => {
            cy.log(`metal.total${xhr.responseBody.meta.total}`);
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@findOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@findDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0')
            .contains('Invoice');

        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0 .sw-data-grid__cell--actions .sw-context-button')
            .click();
        cy.get('.sw-context-menu-item').contains('Download');
    });

    it('@base @order: add document to order with existing invoice number', () => {
        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/**/document/invoice`,
            method: 'post'
        }).as('createDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/document`,
            method: 'post'
        }).as('findDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('findOrder');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/number-range/reserve/document_invoice/*`,
            method: 'get'
        }).as('reserveDocumentNumberRange');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        // Find documents
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-document-grid-button',
            null,
            'Invoice'
        );

        cy.wait('@reserveDocumentNumberRange').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Generate first invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');

        cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
            .then((invoiceNumber) => {
                cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
                    .click();

                // Verify first invoice
                cy.wait('@createDocumentCall').then((xhr) => {
                    expect(xhr).to.have.property('status', 200);
                    expect(xhr.responseBody).to.have.property('documentId');
                    expect(xhr.responseBody).to.have.property('documentDeepLink');

                    const documentId = xhr.response.body.documentId;
                    const documentDeepLink = xhr.response.body.documentDeepLink;

                    cy.getCookie('bearerAuth').then((bearerAuth) => {
                        return JSON.parse(bearerAuth.value).access;
                    }).then((authToken) => {
                        return cy.request(
                            {
                                headers: {
                                    Accept: 'application/vnd.api+json',
                                    Authorization: `Bearer ${authToken}`,
                                    'Content-Type': 'application/json'
                                },
                                method: 'GET',
                                url: `/api/_action/document/${documentId}/${documentDeepLink}`
                            }
                        );
                    }).then((xhrDeepLink) => {
                        expect(xhrDeepLink).to.have.property('status', 200);
                        expect(xhrDeepLink.headers).to.have.property('content-type', 'application/pdf');
                    });
                });

                cy.wait('@findDocumentCall').then((xhr) => {
                    cy.log(`metal.total${xhr.responseBody.meta.total}`);
                    expect(xhr).to.have.property('status', 200);
                });

                cy.wait('@findOrder').then((xhr) => {
                    expect(xhr).to.have.property('status', 200);
                });

                cy.wait('@findDocumentCall').then((xhr) => {
                    expect(xhr).to.have.property('status', 200);
                });

                // Start to create a second invoice
                cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
                cy.get('.sw-order-detail-base__document-grid').should('be.visible');
                cy.clickContextMenuItem(
                    '.sw-context-menu-item',
                    '.sw-order-document-grid-button',
                    null,
                    'Invoice'
                );

                cy.wait('@reserveDocumentNumberRange')
                    .then((xhr) => {
                        expect(xhr).to.have.property('status', 200);
                    });

                // Generate second invoice with same invoice number with first invoice
                cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
                cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
                    .then((invoiceNumberCheck) => {
                        if (invoiceNumberCheck !== invoiceNumber) {
                            cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                        }
                    });
                cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
                    .click();
                // Verify second invoice and check error notification
                cy.wait('@createDocumentCall').then((xhr) => {
                    expect(xhr).to.have.property('status', 400);
                });
                cy.awaitAndCheckNotification(`Document number ${invoiceNumber} has already been allocated.`);
            });
    });
});
