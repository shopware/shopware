describe('Check out of stock variants', () => {
    beforeEach(() => {
        cy.createSalesChannelFixture().then(() => {
            cy.createProductVariantFixture().then(() => {
                cy.setVariantVisibility();
            }).then(() => {
                cy.visit('/Test-product/a.1');
            });
        });
    });

    it('@base @checkout: should gray out variant if they\'re out of stock or not in sales channel', () => {
        // Product detail
        cy.contains('.product-detail-name', 'Test product').should('be.visible');

        // option red should be available and selected
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label.is-combinable')
            .contains('Red')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option xl should be available and selected
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label.is-combinable')
            .contains('XL')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option blue should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Blue')
            .should('have.class', 'is-combinable');

        // option l should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('L')
            .should('have.class', 'is-combinable');

        // option green is sold out and should be grayed out
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Green')
            .should('not.have.class', 'is-combinable');

        // Add to cart button should be visible
        cy.contains('.btn', 'Add to shopping cart').should('be.visible');

        // click sold out option green - xl
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Green')
            .click();

        // should show out of stock notice and no button
        cy.contains('.delivery-information', 'No longer available').should('be.visible');
        cy.contains('.btn', 'Add to shopping cart').should('not.exist');

        // option green should be grayed out and selected
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .not('.is-combinable')
            .contains('Green')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option xl should be grayed out and selected
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .not('.is-combinable')
            .contains('XL')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option red should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Red')
            .should('have.class', 'is-combinable');

        // option blue should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Blue')
            .should('have.class', 'is-combinable');

        // option l should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('L')
            .should('have.class', 'is-combinable');

        // click available option blue - xl
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Blue')
            .click();

        // option red should be available
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Red')
            .should('have.class', 'is-combinable');

        // option xl should be available and selected
        cy.getAttached('.product-detail-configurator-option-label.is-combinable[title="XL"]')
            .should('be.visible');

        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('XL')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option blue should be available and selected
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label.is-combinable')
            .contains('Blue')
            .parent()
            .find('.product-detail-configurator-option-input')
            .should('be.checked');

        // option green is sold out and should be grayed out
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('Green')
        cy.getAttached('.product-detail-configurator-option-label[title="Green"]')
            .should('not.have.class', 'is-combinable');

        // option l is not available for this sales channel
        cy.getAttached('.product-detail-configurator-option-label.is-combinable[title="L"]')
            .should('be.visible');

        // click option l
        cy.get('.product-detail-configurator > form').last()
            .find('.product-detail-configurator-option-label')
            .contains('L')
            .click();

        // should be redirected to different option
        cy.get('.product-detail-ordernumber').should('not.eq', 'a.5');
    });
});

