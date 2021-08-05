/// <reference types="Cypress" />

describe('Test allowBuyInListing config setting', () => {

    beforeEach(() => {
        let ruleId;         
        
        return cy.loginViaApi().then(() => {
            return cy.searchViaAdminApi({
                data: {
                    field: 'name',
                    value: 'Always valid (Default)',
                },
                endpoint: 'rule',
            }); 
        }).then(rule => {
            ruleId = rule.id;

            return cy.fixture('buy-button-products.json')
        }).then(products => {
            products[0].prices[0].ruleId = ruleId;

            products.forEach(product => {
                cy.createProductFixture(product);
            });            
        });
    });
    
    function setAllowBuyInListing(isAllowed) {
        cy.visit('/admin#/sw/settings/listing/index');
        cy.get('.smart-bar__content h2').contains('Settings Products');
        cy.get('#salesChannelSelect .sw-entity-single-select__selection-text').contains('All Sales Channels');

        cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing label').contains('Display buy buttons in listings');
        cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing input').should('be.checked');

        if (!isAllowed) {
            cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing input').click()
                .should('not.be.checked');

            cy.get('.sw-button-process__content').click()
        }
    }  

    it('Should display buy button', () => {
        setAllowBuyInListing(true);

        cy.visit('/')

        cy.get('.card-body:nth(2)').scrollIntoView()

        cy.get('.product-action:nth(0) .btn')
            .should('be.visible')
            .contains('Details')
            .should('have.class', 'btn-light');

        cy.get('.product-action:nth(1) .btn')
            .should('be.visible')
            .contains('Details')
            .should('have.class', 'btn-light');
        
        cy.get('.product-action:nth(2) .btn')
            .should('be.visible')
            .contains('Add to shopping cart')
            .should('have.class', 'btn-buy');
    });

    it('Shouldn\'t display buy button, but should display detail button', () => {
        setAllowBuyInListing(false);
        
        cy.visit('/')

        cy.get('.card-body:nth(2)').scrollIntoView()

        cy.get('.product-action:nth(0) .btn')
            .should('be.visible')
            .contains('Details')
            .should('have.class', 'btn-light');

        cy.get('.product-action:nth(1) .btn')
            .should('be.visible')
            .contains('Details')
            .should('have.class', 'btn-light');
        
        cy.get('.product-action:nth(2) .btn')
            .should('be.visible')
            .contains('Details')
            .should('have.class', 'btn-light');
    });
});
