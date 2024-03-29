let product = {};

describe('Test review function on Product page', () => {
    beforeEach(() => {
        return cy.createMultipleReviewsFixture().then((response) => {
            product = response.product;
            return cy.createDefaultFixture('category');
        }).then(() => {
            cy.loginByGuestAccountViaApi();
        }).then(() => {
            cy.visit('/');
        });
    });

    it('Should show login form when guest review product', { tags: ['pa-content-management'] }, () => {
        cy.get('.header-search-input').should('be.visible');
        cy.get('.header-search-input').type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-price').contains(product.price[0].gross);
        cy.get('.search-suggest-product-name').click();
        cy.get('.review-tab').click();

        cy.get('button.product-detail-review-teaser-btn').contains('Write review').click();

        cy.get('.product-detail-review-login .login-form').should('be.visible');
    });

    it('Should paginate and filter reviews', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.intercept({
            method: 'POST',
            url: '/product/**/reviews*',
        }).as('loadReviews');

        cy.get('.header-search-input').should('be.visible');
        cy.get('.header-search-input').type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-name').click();
        cy.get('.review-tab').click();

        // Ensure 10 reviews on initial page
        cy.get('#review-list').find('.product-detail-review-list-content').should('have.length', 10);

        // Scroll to pagination
        cy.get('.pagination-nav').scrollIntoView();
        cy.get('.pagination-nav').should('be.visible');

        // Navigate to page 2
        cy.get('.page-item.page-next').click();

        // Ensure loading has finished
        cy.wait('@loadReviews')
            .its('response.statusCode').should('eq', 200);
        cy.get('.element-loader-backdrop').should('not.exist');

        // Ensure 2 remaining reviews on page 2
        cy.get('#review-list').find('.product-detail-review-list-content').should('have.length', 2);

        // Navigate back to page 1
        cy.get('.page-item.page-prev').click();

        // Ensure loading has finished
        cy.wait('@loadReviews')
            .its('response.statusCode').should('eq', 200);
        cy.get('.element-loader-backdrop').should('not.exist');

        // Ensure 10 reviews on page 1
        cy.get('#review-list').find('.product-detail-review-list-content').should('have.length', 10);

        // Filter only 4-star reviews
        cy.get('input[type="checkbox"][id="reviewRating4"]').click({ force: true });

        // Ensure loading has finished
        cy.wait('@loadReviews')
            .its('response.statusCode').should('eq', 200);
        cy.get('.element-loader-backdrop').should('not.exist');

        // Ensure only 4-star reviews are displayed
        cy.get('#review-list').find('.product-detail-review-list-content').should('have.length', 1);

        // Remove 4-star filter
        cy.get('input[type="checkbox"][id="reviewRating4"]').click({ force: true });

        // Ensure loading has finished
        cy.wait('@loadReviews')
            .its('response.statusCode').should('eq', 200);
        cy.get('.element-loader-backdrop').should('not.exist');

        // Ensure 10 reviews without filter
        cy.get('#review-list').find('.product-detail-review-list-content').should('have.length', 10);
    });
});
