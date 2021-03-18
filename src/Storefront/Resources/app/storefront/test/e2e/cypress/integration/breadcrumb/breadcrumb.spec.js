describe('Test if breadcrumb works correctly', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.searchViaAdminApi({
                    endpoint: 'category',
                    data: {
                        field: 'name',
                        value: 'Home'
                    }
                }).then(({id: categoryId}) => {
                    cy.createCategoryFixture({
                        name: 'Test category 1',
                        type: 'folder',
                        parentId: categoryId,
                        children: [
                            {
                                name: 'Sub 1',
                                type: 'page'
                            }
                        ]
                    });

                    cy.createCategoryFixture({
                        name: 'Test category 2',
                        type: 'page',
                        parentId: categoryId,
                        children: [
                            {
                                name: 'Sub 2',
                                type: 'page'
                            }
                        ]
                    });

                    cy.createCategoryFixture({
                        name: 'Test category 3',
                        type: 'link',
                        parentId: categoryId,
                        children: [
                            {
                                name: 'Sub 3',
                                type: 'page'
                            }
                        ]
                    });
                });
            }).then(() => {
                cy.visit('/');
            });
    });

    it('@breadcrumb: Check if correct category types are clickable', () => {
        cy.get('.nav-link.main-navigation-link').contains('Test category 1').trigger('mouseenter').then(() => {
            cy.get('.navigation-flyout-content').should('be.visible');
            cy.get('.nav-link.navigation-flyout-link').contains('Sub 1').click();

            cy.get('.cms-breadcrumb .breadcrumb').contains('Test category 1').should('have.prop', 'tagName' ).should('eq', 'DIV');
        });

        cy.get('.nav-link.main-navigation-link').contains('Test category 2').trigger('mouseenter').then(() => {
            cy.get('.navigation-flyout-content').should('be.visible');
            cy.get('.nav-link.navigation-flyout-link').contains('Sub 2').click();

            cy.get('.cms-breadcrumb .breadcrumb').contains('Test category 2').should('have.prop', 'tagName' ).should('eq', 'A');
        });

        cy.get('.nav-link.main-navigation-link').contains('Test category 3').trigger('mouseenter').then(() => {
            cy.get('.navigation-flyout-content').should('be.visible');
            cy.get('.nav-link.navigation-flyout-link').contains('Sub 3').click();

            cy.get('.cms-breadcrumb .breadcrumb').contains('Test category 3').should('have.prop', 'tagName' ).should('eq', 'A');
        });
    });
});
