/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import NewsletterRecipientPageObject  from '../../support/pages/module/sw-newsletter-recipient.page-object';

describe('Storefront profile settings', () => {
    it('@package: should apply for newsletter in storefront and see the customer in newsletter recipients ', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        cy.intercept({
            url: `/widgets/account/newsletter`,
            method: 'POST',
        }).as('checkNewsletter');

        const page = new NewsletterRecipientPageObject();

        // Login from storefront
        cy.visit('/account/login');
        cy.url().should('include', '/account/login');
        cy.get('#personalSalutation').select('Mrs.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Lisa');
        cy.get('#personalLastName').typeAndCheckStorefront('Hoffmann');
        cy.get('#personalMail').typeAndCheckStorefront('lisa@hoffmann.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Amsterdam');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Newsletter subscription
        cy.url().should('include', '/account');
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
        cy.get('label[for="newsletterRegister"]').click();
        cy.wait('@checkNewsletter').its('response.statusCode').should('equal', 200);
        cy.contains('You have successfully subscribed to the newsletter.').should('exist');

        // Verify the subscription from the newsletter recipients
        cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', '/newsletter/recipient/index');
        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Nieuwsbriefontvanger');
        cy.contains(`${page.elements.dataGridRow}--0 a`, 'lisa@hoffmann.com');
    });
});
