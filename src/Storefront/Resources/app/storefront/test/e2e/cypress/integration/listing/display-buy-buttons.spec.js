describe('Test allowBuyInListing config setting', () => {
    function setAllowBuyInListing(isAllowed) {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi().then(() => {
                    cy.createProductFixture();

                    cy.openInitialPage('/admin#/sw/settings/listing/index');
                    cy.get('.smart-bar__content h2').contains('Settings Products');
                    cy.get('#salesChannelSelect .sw-entity-single-select__selection-text').contains('All Sales Channels');

                    cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing label').contains('Display buy buttons in listings');
                    cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing input').should('be.checked');

                    if (!isAllowed) {
                        cy.get('.sw-system-config--field-core-listing-allow-buy-in-listing input').click()
                            .should('not.be.checked');

                        cy.get('.sw-button-process__content').click()
                    }
                });
            })
    }

    it('Should display buy button', () => {
        setAllowBuyInListing(true);

        cy.visit('/')

        cy.get('.buy-widget > .btn').should('exist').should('be.visible')
        cy.get('.product-action > .btn').should('not.exist')
    });

    it("Shouldn't display buy button, but should display detail button", () => {
        setAllowBuyInListing(false);
        cy.visit('/')

        cy.get('.buy-widget > .btn').should('not.exist')
        cy.get('.product-action > .btn').should('exist').should('be.visible')
    });
});
