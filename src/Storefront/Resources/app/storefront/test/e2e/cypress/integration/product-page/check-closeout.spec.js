describe('Check out of stock variants', () => {
    beforeEach(() => {
        cy.createProductVariantFixture().then(() => {
            cy.visit('/Test-product/a.1');
        });
    });

    it('@base @checkout: should grey out variant if they\'re out of stock', () => {
        // Product detail
        cy.contains('.product-detail-name', 'Test product').should('be.visible');
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option').last()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');
        cy.contains('.btn', 'Add to shopping cart').should('be.visible');

        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option').first()
            .find('.product-detail-configurator-option-label.is-combinable')
            .should('not.exist');

        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option').first()
            .find('.product-detail-configurator-option-label')
            .click();

        cy.contains('.delivery-information', 'No longer available').should('be.visible');
        cy.get('.product-detail-configurator-option-label.is-combinable').should('be.visible');
        cy.contains('.btn', 'Add to shopping cart').should('not.exist');
    });
});

