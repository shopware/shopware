/**
 * @package content
 */
// / <reference types="Cypress" />

describe('Category: Test drag categories', () => {
    beforeEach(() => {
        cy.searchViaAdminApi({
            endpoint: 'category',
            data: {
                field: 'name',
                value: 'Home',
            },
        })
            .then(({ id: categoryId }) => {
                cy.createCategoryFixture({
                    name: 'Child 1',
                    type: 'folder',
                    parentId: categoryId,
                    children: [
                        {
                            name: 'Grandchild',
                            type: 'page',
                        },
                    ],
                })
                    .then(({ id: childId }) => {
                        cy.createCategoryFixture({
                            name: 'Child 2',
                            type: 'page',
                            parentId: categoryId,
                            afterCategoryId: childId,
                        });
                    });
            })
            .then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: can drag category and expand', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');

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
        cy.wait('@loadCategory').its('response.statusCode').should('equals', 200);

        // test that grandchildren of Child 1 are visible
        cy.get('.tree-items > .sw-tree-item > .sw-tree-item__children > .sw-tree-item > .sw-tree-item__children')
            .should('be.visible')
            .contains('Grandchild');
    });
});
