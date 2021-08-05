// / <reference types="Cypress" />

describe('Category: Test drag categories', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.searchViaAdminApi({
                    endpoint: 'category',
                    data: {
                        field: 'name',
                        value: 'Home'
                    }
                });
            })
            .then(({ id: categoryId }) => {
                cy.createCategoryFixture({
                    name: 'Child 1',
                    type: 'folder',
                    parentId: categoryId,
                    children: [
                        {
                            name: 'Grandchild',
                            type: 'page'
                        }
                    ]
                })
                .then(({ id: childId }) => {
                    cy.createCategoryFixture({
                        name: 'Child 2',
                        type: 'page',
                        parentId: categoryId,
                        afterCategoryId: childId
                    });
                });
            })
            .then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@base @catalogue: can drag category and expand', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'post'
        }).as('loadCategory');

        //expand home category
        cy.get('.tree-items > .sw-tree-item')
            .eq(0)
            .find('.sw-tree-item__toggle')
            .click();

        cy.wait('@loadCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // drag Child 1 to second position
        cy.get('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item')
            .eq(0)
            .dragTo('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item:nth-child(2) .sw-tree-item__element');

        // test that Child 1 is at new position
        cy.get('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item')
            .eq(1)
            .contains('Child 1');

        // test that Child 1 at new position can be expanded
        cy.get('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item')
            .eq(1)
            .find('.sw-tree-item__toggle')
            .click();

        cy.wait('@loadCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // test that grandchildren of Child 1 are visible
        cy.get('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item > .sw-tree-item__children')
            .should('be.visible')
            .contains('Grandchild');
    });
});
