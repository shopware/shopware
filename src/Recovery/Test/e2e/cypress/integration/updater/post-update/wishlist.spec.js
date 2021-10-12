/// <reference types="Cypress" />

describe('check wishlist after update', ()=>{

    // dependent pre-update > wishlist.spec.js
    it('@post-update should contain wishlist-product', ()=>{

        // login
        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('markus.stein@test.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // check wishlist
        cy.visit('/wishlist');
        cy.get('[data-wishlist-storage]').contains('1').should('be.visible')
        let productName = 'Wishlist Test'
        cy.get('.card-body-wishlist').contains(productName);
    });
});
