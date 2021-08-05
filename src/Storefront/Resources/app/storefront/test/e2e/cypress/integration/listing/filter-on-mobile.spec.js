import product1 from '../../fixtures/variantProduct1';
import product2 from '../../fixtures/variantProduct2';

let filterBy = {
    manufacturer: 'shopware AG',
    color: 'red',
    size: 'L'
}

describe('Listing: Filter on mobile', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture(product1);
            })
            .then(() => {
                return cy.createProductFixture(product2);
            })
            .then(() => {
                cy.visit('/');
            });
    });

    function verifySelectedFilter() {
        cy.get('.filter-active').contains(filterBy.manufacturer).should('have.length', 1)
        cy.get('.filter-active').contains(filterBy.color).should('have.length', 1)
        cy.get('.filter-active').contains(filterBy.size).should('have.length', 1)
    }

    it('Should keep filtered values when clicking on close button', () => {
        // set to mobile viewport
        cy.viewport(360, 640)

        cy.get('.filter-panel-wrapper-toggle').click()

        // select manufacture
        cy.get('.filter-multi-select-manufacturer').as('manufacturerFilter');

        cy.get('@manufacturerFilter').click();
        cy.get('.filter-multi-select-manufacturer .filter-multi-select-dropdown')
            .as('manufacturerList').should('be.visible');

        // filter by shopware AG manufacturer
        cy.get('@manufacturerList').contains(filterBy.manufacturer).click();

        // close manufacturer dropdown
        cy.get('@manufacturerFilter').click();

        // color filter
        cy.get('.filter-multi-select-properties').contains('color').as('colorFilterButton');

        cy.get('@colorFilterButton').closest('.filter-multi-select-properties').as('colorFilter');
        cy.get('@colorFilterButton').click();
        cy.get('@colorFilter').within(() => {
            cy.get('.filter-multi-select-dropdown').as('colorList').should('be.visible')
        })

        // filter by red color
        cy.get('@colorList').contains(filterBy.color).click();

        // close color dropdown
        cy.get('@colorFilterButton').click();

        // size filter
        cy.get('.filter-multi-select-properties').contains('size').as('sizeFilterButton');

        cy.get('@sizeFilterButton').closest('.filter-multi-select-properties').as('sizeFilter');
        cy.get('@sizeFilterButton').click()
        cy.get('@sizeFilter').within(() => {
            cy.get('.filter-multi-select-dropdown').as('sizeList').should('be.visible')
        });
        cy.get('@sizeList').contains(filterBy.size).click();
        // close size dropdown
        cy.get('@sizeFilterButton').click();

        // before closing filter
        verifySelectedFilter();

        cy.get('.offcanvas-filter').within(() => {
            cy.get('.filter-panel-offcanvas-close').click();
        })

        // reopen filter panel
        cy.get('.filter-panel-wrapper-toggle').click();

        // after closing filter
        verifySelectedFilter();
    });
});
