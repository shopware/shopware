// / <reference types="Cypress" />

import NewsletterRecipientPageObject from '../../../support/pages/module/sw-newsletter-recipient.page-object';

describe('Newsletter-Recipient: Test crud operations with ACL', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.createNewsletterRecipientFixture({
                email: 'max.mustermann@example.com',
                firstName: 'Max',
                lastName: 'Mustermann',
                street: 'Buchenweg 5',
                zipcode: '33602',
                city: 'Bielefeld'
            });
        }).then(() => {
            cy.loginViaApi();
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });
    });

    // TODO Unskip if NEXT-11444 is fixed
    it.skip('@marketing: read NewsletterRecipient with ACL, but without rights', () => {
        cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);
        cy.get('.sw-data-grid__cell--email').click();
        cy.location('hash').then(hash => {
            cy.loginAsUserWithPermissions([]);

            cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);
            cy.location('hash').should('eq', '#/sw/privilege/error/index');

            cy.visit(Cypress.env('admin') + hash);
            cy.location('hash').should('eq', '#/sw/privilege/error/index');
        });
    });

    // TODO Unskip if NEXT-11444 is fixed
    it.skip('@marketing: read NewsletterRecipient with ACL', () => {
        const page = new NewsletterRecipientPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'newsletter_recipient',
                role: 'viewer'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);

        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Newsletter recipients');
        cy.get(`${page.elements.dataGridRow}--0 a`).click();

        cy.get(page.elements.newsletteSave).should('be.disabled');
    });

    // TODO Unskip if NEXT-11444 is fixed
    it.skip('@marketing: edit and read NewsletterRecipient with ACL', () => {
        const page = new NewsletterRecipientPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'newsletter_recipient',
                role: 'viewer'
            },
            {
                key: 'newsletter_recipient',
                role: 'editor'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/newsletter-recipient/**`,
            method: 'PATCH'
        }).as('saveData');

        // Edit base data
        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Newsletter recipients');
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=sw-field--newsletterRecipient-title]').clearTypeAndCheck('Mister');
        cy.get(page.elements.newsletteSave).should('not.be.disabled');
        cy.get(page.elements.newsletteSave).click();

        // Verify updated manufacturer
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-alert__title').contains('Success');
    });

    // TODO Unskip if NEXT-11444 is fixed
    it.skip('@marketing: delete NewsletterRecipient with ACL', () => {
        const page = new NewsletterRecipientPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'newsletter_recipient',
                role: 'viewer'
            },
            {
                key: 'newsletter_recipient',
                role: 'editor'
            },
            {
                key: 'newsletter_recipient',
                role: 'deleter'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/newsletter/recipient/index`);

        // check that NewsletterRecipient exists
        cy.contains('Mustermann').should('exist');

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/newsletter-recipient/**`,
            method: 'delete'
        }).as('saveData');

        // Delete manufacturer
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} ${page.elements.modal}__body p`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify updated manufacturer
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.contains('Mustermann').should('not.exist');
    });
});
