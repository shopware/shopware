import variantProduct from '../../fixtures/variantProductListingPrice';

describe('Test if listingPrice is shown even on Variantes with differing prices', () => {
    beforeEach(() => {
        cy.createProductFixture(variantProduct).then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            cy.visit('/');
        });
    });

    it('Should show listingPrice (Streichpreis)', () => {
        // go to first product
        cy.visit('/Variant-product/TEST.1');
        // check if normal price is correct
        cy.get('.product-detail-price').contains('€110.00*');
        // check if listingPrice/streichpreis is correct
        cy.get('.list-price-price').contains('€120.00');

        // go to second product
        cy.visit('/Variant-product/TEST.2');
        // check if normal price is correct
        cy.get('.product-detail-price').contains('€110.00*');
        // check if listingPrice/streichpreis is correct
        cy.get('.list-price-price').contains('€120.00');

        // go to third product
        cy.visit('/Variant-product/TEST.3');
        // check if normal price is correct
        cy.get('.product-detail-price').contains('€130.00*');
        // check if listingPrice/streichpreis is correct
        cy.get('.list-price-price').contains('€140.00');
    });
});
