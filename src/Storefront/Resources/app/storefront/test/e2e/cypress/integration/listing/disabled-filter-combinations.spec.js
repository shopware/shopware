import variant1 from '../../fixtures/variantProduct1';
import variant2 from '../../fixtures/variantProduct2';

describe('Test product filters get disabled if a combination is not possible', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi().then(() => {
                    cy.visit('/admin#/sw/settings/listing/index');
                    cy.contains('Disable filter options without results').click();
                    cy.get('.sw-settings-listing__save-action').click();
                    cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
                });
            })
            .then(() => {
                return cy.createProductFixture(variant1);
            })
            .then(() => {
                return cy.createProductFixture(variant2);
            })
            .then(() => {
                cy.visit('/');
            });
    });

    it('Should disable some filters if filtered by manufacturer', () => {
        cy.get('.filter-multi-select-properties').contains('color').as('colorFilterButton');
        cy.get('@colorFilterButton').closest('.filter-multi-select-properties').as('colorFilter');

        cy.get('.filter-multi-select-properties').contains('size').as('sizeFilterButton');
        cy.get('@sizeFilterButton').closest('.filter-multi-select-properties').as('sizeFilter');

        cy.get('.filter-boolean').contains('Free shipping').as('shippingFilterLabel');
        cy.get('@shippingFilterLabel').closest('.filter-boolean').as('shippingFilter');

        cy.get('.filter-multi-select-manufacturer').as('manufacturerFilter');

        cy.get('@manufacturerFilter').click();
        cy.get('.filter-multi-select-manufacturer .filter-multi-select-dropdown')
            .as('manufacturerList').should('be.visible');

        // Filter by manufacturer shopware AG
        cy.get('@manufacturerList').contains('shopware AG').click();

        // Close manufacturer dropdown
        cy.get('@manufacturerFilter').click();

        // Check that only element 1 and 2 of the color filter are disabled
        cy.get('@colorFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        cy.get('@sizeFilterButton')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        // Check all size filter items are not disabled
        cy.get('@sizeFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        // Check shipping filter is not disabled
        cy.get('@shippingFilter')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        // Reset all button
        cy.get('.filter-reset-all').click();

        // Check if all filters are reset
        cy.get('@colorFilterButton')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        cy.get('@sizeFilterButton')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        cy.get('@shippingFilter')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        cy.get('@manufacturerList').within(() => {
            cy.get('li').should('have.length', 2).each((elem) => {
                expect(elem).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });
    });

    it('Should disable some filters if filtered by size', () => {
        cy.get('.filter-multi-select-properties').contains('color').as('colorFilterButton');
        cy.get('@colorFilterButton').closest('.filter-multi-select-properties').as('colorFilter');

        cy.get('.filter-multi-select-properties').contains('size').as('sizeFilterButton');
        cy.get('@sizeFilterButton').closest('.filter-multi-select-properties').as('sizeFilter');

        cy.get('.filter-boolean').contains('Free shipping').as('shippingFilterLabel');
        cy.get('@shippingFilterLabel').closest('.filter-boolean').as('shippingFilter');

        cy.get('.filter-multi-select-manufacturer').as('manufacturerFilter');

        cy.get('@sizeFilterButton').click();

        // Filter by size
        cy.get('.filter-multi-select-dropdown').contains('M').click();

        // Close size dropdown
        cy.get('@sizeFilterButton').click();

        // Only the first manufacturer should be active
        cy.get('@manufacturerFilter').within(() => {
            cy.get('li').should('have.length', 2).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return classList.includes('disabled');
                });
            });
        });

        // Check that only element 1 and 2 of the color filter are disabled
        cy.get('@colorFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        // Shipping filter not disabled
        cy.get('@shippingFilter')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return !classList.includes('disabled');
            });

        // Reset all button
        cy.get('.filter-reset-all').click();

        // Check all size filter items are not disabled
        cy.get('@sizeFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        // Check all color filter items are not disabled
        cy.get('@colorFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        // Check no manufacturer is disabled
        cy.get('@manufacturerFilter').within(() => {
            cy.get('li').should('have.length', 2).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });
    });

    it('Should disable free shipping filter', () => {
        cy.get('.filter-multi-select-properties').contains('color').as('colorFilterButton');
        cy.get('@colorFilterButton').closest('.filter-multi-select-properties').as('colorFilter');

        cy.get('.filter-multi-select-properties').contains('size').as('sizeFilterButton');
        cy.get('@sizeFilterButton').closest('.filter-multi-select-properties').as('sizeFilter');

        cy.get('.filter-boolean').contains('Free shipping').as('shippingFilterLabel');
        cy.get('@shippingFilterLabel').closest('.filter-boolean').as('shippingFilter');

        cy.get('.filter-multi-select-manufacturer').as('manufacturerFilter');

        cy.get('@manufacturerFilter').click();
        cy.get('.filter-multi-select-manufacturer .filter-multi-select-dropdown')
            .as('manufacturerList').should('be.visible');

        // Filter by manufacturer Test variant manufacturer
        cy.get('@manufacturerList').contains('Test variant manufacturer').click();

        // Close manufacturer dropdown
        cy.get('@manufacturerFilter').click();

        // Check all color filter items are not disabled
        cy.get('@colorFilter').within(() => {
            cy.get('li').should('have.length', 3).should((elements) => {
                expect(elements.eq(0)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(1)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
                expect(elements.eq(2)).to.satisfy(($el) => {
                    const classList = Array.from($el[0].classList);
                    return !classList.includes('disabled');
                });
            });
        });

        // Shipping filter is disabled
        cy.get('@shippingFilter')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return classList.includes('disabled');
            });

        // Check color filter is disabled
        cy.get('@sizeFilterButton')
            .should('satisfy', ($el) => {
                const classList = Array.from($el[0].classList);
                return classList.includes('disabled');
            });
    });
});
