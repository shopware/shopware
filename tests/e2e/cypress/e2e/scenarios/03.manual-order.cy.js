// / <reference types="Cypress" />

import CustomerPageObject from '../../support/pages/module/sw-customer.page-object';
import OrderPageObject from '../../support/pages/module/sw-order.page-object';

describe('Create customer via UI, product via API and make a manual order', ()=>{
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.createProductFixture({
                    name: 'Test Product',
                    productNumber: 'TEST-1234',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 8,
                            linked: true,
                            gross: 10,
                        },
                    ],
                });
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@package: should create manual order and use credit', { tags: ['pa-customers-orders'] }, ()=> {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('saveProduct');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('salesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/customer`,
            method: 'POST',
        }).as('save-customer');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('user-config');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST',
        }).as('save-proxy');

        const customerPage = new CustomerPageObject();
        const orderPage = new OrderPageObject();
        const salesChannel = Cypress.env('storefrontName');

        // create new customer via UI
        cy.fixture('customer-scenario3').then(customer => {
            cy.get('.sw-button.sw-button--primary.sw-customer-list__button-create').click();
            cy.contains('h2', 'Nieuwe klant').should('be.visible');
            cy.get('.sw-customer-base-form__account-type-select')
                .typeSingleSelectAndCheck('Bedrijf', '.sw-customer-base-form__account-type-select');
            cy.get('.sw-customer-base-form__salutation-select')
                .typeSingleSelectAndCheck('Mr.', '.sw-customer-base-form__salutation-select');
            cy.get('input[name=sw-field--customer-firstName]').type(customer.firstName);
            cy.get('input[name=sw-field--customer-lastName]').type(customer.lastName);
            cy.get('input[name=sw-field--customer-email]').type(customer.email);
            cy.get('input[name=sw-field--customer-password]').type('shopware');
            cy.get('.sw-customer-base-form__customer-group-select')
                .typeSingleSelectAndCheck(
                    'Standard customer group',
                    '.sw-customer-base-form__customer-group-select',
                );
            cy.get('.sw-customer-base-form__sales-channel-select')
                .typeSingleSelectAndCheck(customer.salesChannel, '.sw-customer-base-form__sales-channel-select');
            cy.get('.sw-customer-base-form__payment-method-select')
                .typeSingleSelectAndCheck('Cash on delivery', '.sw-customer-base-form__payment-method-select');
            cy.get('input[name="sw-field--address-company"]').type('Test Company');
            cy.get('input[name="sw-field--address-department"]').type('Test Department');
            cy.get('.sw-customer-address-form__salutation-select')
                .typeSingleSelectAndCheck('Mr.', '.sw-customer-address-form__salutation-select');
            cy.get('input[name=sw-field--address-firstName]').type(customer.firstName);
            cy.get('input[name=sw-field--address-lastName]').type(customer.lastName);
            cy.get('input[name=sw-field--address-street]').type('Maxwell str.');
            cy.get('input[name=sw-field--address-zipcode]').type('1019');
            cy.get('input[name=sw-field--address-city]').type('Amsterdam');
            cy.get('.sw-customer-address-form__country-select')
                .typeSingleSelectAndCheck('Netherlands', '.sw-customer-address-form__country-select');
            cy.get(customerPage.elements.customerSaveAction).click();
            cy.wait('@save-customer').its('response.statusCode').should('equal', 204);
        });

        // add product to the sales channel
        cy.contains(salesChannel).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 204);
        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@salesChannel').its('response.statusCode').should('equal', 200);

        // make manual order
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.contains('.sw-empty-state__title', 'Nog geen bestellingen');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'order/index');
        cy.get('.sw-button.sw-button--primary.sw-order-list__add-order').click();

        cy.get(".sw-order-create-initial-modal").contains("Nieuwe bestelling").should("be.visible");
        cy.contains(".sw-data-grid__row", "Martin Maxwell").find("input[type=radio]").check();
        cy.get(".sw-button--primary").click();

        cy.contains('.smart-bar__header', 'Nieuwe bestelling');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(".sw-order-create-general").should("be.visible").contains("Martin Maxwell");
        cy.contains('Product toevoegen').click();
        cy.get(`${orderPage.elements.dataGridRow}--0 ${orderPage.elements.dataGridColumn}--label`).dblclick();
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Test Product', '.sw-order-product-select__single-select');
        cy.get(`${orderPage.elements.dataGridColumn}--quantity input`).clearTypeAndCheck('1');
        cy.get(`${orderPage.elements.dataGridInlineEditSave}`).click();
        cy.get('.sw-description-list > :nth-child(2)').should('include.text', '10,00');
        cy.wait('@user-config').its('response.statusCode').should('equal', 200);

        // add credit
        cy.get('.sw-context-button > .sw-button').click();
        cy.contains('Creditnota toevoegen').click();
        cy.get(`${orderPage.elements.dataGridRow}--0 ${orderPage.elements.dataGridColumn}--label`)
            .dblclick().type('credit');
        cy.get('[placeholder="0"]').click().type('2');
        cy.get('button[title="Opslaan"]').click();
        cy.contains('.sw-description-list > :nth-child(2)', '8');
        cy.contains('Bestelling opslaan').click();

        // deny payment reminder
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@save-proxy').its('response.statusCode').should('equal', 200);
        cy.get('.sw-order-create__remind-payment-modal-decline').click();

        // confirmation
        cy.url().should('include', 'order/detail');
        cy.contains('.smart-bar__header', 'Bestelling');
        cy.get(".sw-order-detail-general").should("be.visible").contains("Martin Maxwell");

        // verify the new customer's order from the products page
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__cell--price-EUR > .sw-data-grid__cell-content')
            .should('include.text', '10,00');

        // verify the new customer's order from the order page
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('h2', 'Bestellingen').should('be.visible');
        cy.get('.sw-order-list__manual-order-label .sw-label__caption')
            .should('include.text', 'Gemaakt door admin');
        cy.get('.sw-data-grid__cell--amountTotal > .sw-data-grid__cell-content').should('be.visible')
            .and('include.text', '8,00');
    });
});
